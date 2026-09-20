<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Models\Game;
use App\Models\InventoryBalance;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseOrder
{
    public function __construct(private readonly InventoryCapacityService $inventoryCapacity) {}

    public function execute(Game $game, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($game, $purchaseOrder) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $company = $lockedGame->company()->lockForUpdate()->firstOrFail();
            $order = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($order->company_id !== $company->id) {
                throw ValidationException::withMessages(['purchase_order' => 'Pedido inválido para esta partida.']);
            }

            if ($order->status === 'received') {
                return $order->load('supplier', 'items.product');
            }

            if ($order->status !== 'ordered' || $lockedGame->current_date->isBefore($order->expected_delivery_date)) {
                throw ValidationException::withMessages(['purchase_order' => 'Este pedido ainda não pode ser recebido.']);
            }

            $this->inventoryCapacity->assertWithinCapacity($company);

            foreach ($order->items as $item) {
                $balance = InventoryBalance::query()
                    ->where('company_id', $company->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $newQuantity = $balance->quantity + $item->quantity;
                $totalCostCents = ($balance->quantity * $balance->average_cost_cents) + $item->total_cents;
                $averageCostCents = intdiv($totalCostCents + intdiv($newQuantity, 2), $newQuantity);

                $balance->update([
                    'quantity' => $newQuantity,
                    'average_cost_cents' => $averageCostCents,
                ]);

                $order->inventoryMovements()->create([
                    'company_id' => $company->id,
                    'product_id' => $item->product_id,
                    'type' => 'purchase_receipt',
                    'quantity' => $item->quantity,
                    'unit_cost_cents' => $item->unit_cost_cents,
                    'total_cost_cents' => $item->total_cents,
                    'game_date' => $lockedGame->current_date,
                ]);
            }

            $order->update([
                'status' => 'received',
                'received_at_game_date' => $lockedGame->current_date,
            ]);

            return $order->load('supplier', 'items.product');
        });
    }
}
