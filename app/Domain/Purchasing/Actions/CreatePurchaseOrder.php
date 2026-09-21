<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Inbox\Services\InboxService;
use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Models\Employee;
use App\Models\Game;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly InventoryCapacityService $inventoryCapacity,
        private readonly InboxService $inbox,
    ) {}

    /** @param array<int, array{product_id: int, quantity: int}> $items */
    public function execute(Game $game, Supplier $supplier, array $items, ?Employee $buyer = null, bool $automatic = false): PurchaseOrder
    {
        return DB::transaction(function () use ($game, $supplier, $items, $buyer, $automatic) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $company = $lockedGame->company()->lockForUpdate()->firstOrFail();
            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            if ($lockedGame->status !== 'active' || $lockedSupplier->company_id !== $company->id) {
                throw ValidationException::withMessages(['supplier_id' => 'Fornecedor inválido para esta partida.']);
            }

            if ($buyer && ($buyer->company_id !== $company->id || $buyer->status !== 'active')) {
                throw ValidationException::withMessages(['employee_id' => 'Funcionário responsável inválido.']);
            }

            $normalizedItems = collect($items)
                ->filter(fn (array $item) => ($item['quantity'] ?? 0) > 0)
                ->values();

            if ($normalizedItems->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Informe ao menos um produto com quantidade válida.']);
            }

            $productIds = $normalizedItems->pluck('product_id');
            if ($productIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Cada produto deve aparecer apenas uma vez.']);
            }

            $offers = SupplierProduct::query()
                ->where('supplier_id', $lockedSupplier->id)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $pricedItems = $normalizedItems->map(function (array $item) use ($offers) {
                $offer = $offers->get($item['product_id']);
                if (! $offer || $item['quantity'] < $offer->minimum_quantity) {
                    throw ValidationException::withMessages(['items' => 'Produto ou quantidade mínima inválida para o fornecedor.']);
                }

                return [
                    'product_id' => $offer->product_id,
                    'quantity' => $item['quantity'],
                    'unit_cost_cents' => $offer->cost_cents,
                    'total_cents' => $offer->cost_cents * $item['quantity'],
                ];
            });

            $totalCents = $pricedItems->sum('total_cents');
            $this->inventoryCapacity->assertCanReserve($company, (int) $pricedItems->sum('quantity'));
            $isCashPurchase = $lockedSupplier->payment_term_days === 0;

            if ($isCashPurchase && $company->cash_balance_cents < $totalCents) {
                throw ValidationException::withMessages(['items' => 'Caixa insuficiente para pagar este pedido à vista.']);
            }

            $order = $company->purchaseOrders()->create([
                'supplier_id' => $lockedSupplier->id,
                'employee_id' => $buyer?->id,
                'status' => 'ordered',
                'automatic' => $automatic,
                'ordered_at_game_date' => $lockedGame->current_date,
                'expected_delivery_date' => $lockedGame->current_date->copy()->addDays($lockedSupplier->lead_time_days),
                'total_cents' => $totalCents,
            ]);

            $order->items()->createMany($pricedItems->all());

            $financialEntry = $order->financialEntry()->create([
                'company_id' => $company->id,
                'type' => 'outflow',
                'category' => 'purchase',
                'description' => "Pedido de compra #{$order->id}",
                'amount_cents' => $totalCents,
                'game_date' => $lockedGame->current_date,
                'due_date' => $lockedGame->current_date->copy()->addDays($lockedSupplier->payment_term_days),
            ]);

            if ($isCashPurchase) {
                $this->ledger->settle($financialEntry, $lockedGame->current_date);
            }

            $totalUnits = (int) $pricedItems->sum('quantity');
            $this->inbox->sendSystem(
                $lockedGame->user,
                'purchase',
                $automatic ? "Pedido automático #{$order->id} criado" : "Pedido de compra #{$order->id} criado",
                $automatic
                    ? "{$buyer->name} criou uma reposição de {$totalUnits} unidade(s) com {$lockedSupplier->name}."
                    : "Um pedido de {$totalUnits} unidade(s) foi criado com {$lockedSupplier->name}.",
                "/games/{$lockedGame->id}/purchases",
                [
                    'order_id' => $order->id,
                    'supplier_name' => $lockedSupplier->name,
                    'employee_id' => $buyer?->id,
                    'employee_name' => $buyer?->name,
                    'automatic' => $automatic,
                    'total_units' => $totalUnits,
                    'total_cents' => $totalCents,
                    'game_date' => $lockedGame->current_date->toDateString(),
                ],
            );

            return $order->load('supplier', 'items.product', 'financialEntry');
        });
    }
}
