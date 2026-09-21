<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Models\Employee;
use App\Models\Game;
use App\Models\JobRole;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierProduct;
use Illuminate\Validation\ValidationException;

class AutomaticPurchasingService
{
    public function __construct(
        private readonly CreatePurchaseOrder $createPurchaseOrder,
        private readonly InventoryCapacityService $inventoryCapacity,
    ) {}

    /** @return array{order_ids: array<int, int>, units: int, buyers: array<int, string>, details: array<int, array<string, int|string>>} */
    public function process(Game $game): array
    {
        $company = $game->company;
        $roleCapacities = JobRole::query()
            ->where('department', 'Compras')
            ->where('active', true)
            ->pluck('purchasing_capacity_units', 'name');
        $buyers = $company->employees()
            ->where('status', 'active')
            ->where('department', 'Compras')
            ->get()
            ->map(fn ($employee) => [
                'model' => $employee,
                'remaining' => (int) ($roleCapacities[$employee->role] ?? 0),
            ])
            ->filter(fn (array $buyer) => $buyer['remaining'] > 0)
            ->sortByDesc(fn (array $buyer) => $buyer['remaining'])
            ->values();

        if ($buyers->isEmpty()) {
            return $this->emptyResult();
        }

        $incoming = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($query) => $query
                ->where('company_id', $company->id)
                ->where('status', 'ordered'))
            ->selectRaw('product_id, SUM(quantity) AS quantity')
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');
        $candidates = $company->inventoryBalances()
            ->with('product')
            ->get()
            ->map(function ($balance) use ($incoming) {
                $projected = $balance->quantity + (int) ($incoming[$balance->product_id] ?? 0);
                $dailyDemand = max(1, $balance->product->base_daily_demand);
                $reorderPoint = $dailyDemand * config('game.purchasing.reorder_point_days');
                $target = $dailyDemand * config('game.purchasing.target_stock_days');

                return [
                    'product' => $balance->product,
                    'projected' => $projected,
                    'reorder_point' => $reorderPoint,
                    'needed' => max(0, $target - $projected),
                    'coverage_basis_points' => intdiv($projected * 10_000, $dailyDemand),
                ];
            })
            ->filter(fn (array $candidate) => $candidate['projected'] <= $candidate['reorder_point'] && $candidate['needed'] > 0)
            ->sortBy('coverage_basis_points')
            ->values();
        $availableStorage = $this->inventoryCapacity->calculate($company)['available_units'];
        $orderIds = [];
        $details = [];

        foreach ($candidates as $candidate) {
            if ($availableStorage <= 0) {
                break;
            }

            foreach ($buyers as $buyerIndex => $buyer) {
                $quantity = min($candidate['needed'], $buyer['remaining'], $availableStorage);
                if ($quantity <= 0) {
                    continue;
                }

                $order = $this->placeOrder($game, $candidate['product']->id, $quantity, $buyer['model']);
                if (! $order) {
                    continue;
                }

                $orderIds[] = $order->id;
                $details[] = [
                    'order_id' => $order->id,
                    'employee_id' => $buyer['model']->id,
                    'employee_name' => $buyer['model']->name,
                    'product_id' => $candidate['product']->id,
                    'product_name' => $candidate['product']->name,
                    'quantity' => $quantity,
                ];
                $buyers[$buyerIndex] = [...$buyer, 'remaining' => $buyer['remaining'] - $quantity];
                $availableStorage -= $quantity;
                break;
            }
        }

        return [
            'order_ids' => $orderIds,
            'units' => array_sum(array_column($details, 'quantity')),
            'buyers' => array_values(array_unique(array_column($details, 'employee_name'))),
            'details' => $details,
        ];
    }

    private function placeOrder(Game $game, int $productId, int $quantity, Employee $buyer): ?PurchaseOrder
    {
        $offers = SupplierProduct::query()
            ->with('supplier')
            ->where('product_id', $productId)
            ->whereHas('supplier', fn ($query) => $query->where('company_id', $game->company->id))
            ->orderBy('cost_cents')
            ->get();

        foreach ($offers as $offer) {
            if ($quantity < $offer->minimum_quantity) {
                continue;
            }

            try {
                return $this->createPurchaseOrder->execute(
                    $game,
                    $offer->supplier,
                    [['product_id' => $productId, 'quantity' => $quantity]],
                    $buyer,
                    true,
                );
            } catch (ValidationException) {
                continue;
            }
        }

        return null;
    }

    /** @return array{order_ids: array<int, int>, units: int, buyers: array<int, string>, details: array<int, array<string, int|string>>} */
    private function emptyResult(): array
    {
        return ['order_ids' => [], 'units' => 0, 'buyers' => [], 'details' => []];
    }
}
