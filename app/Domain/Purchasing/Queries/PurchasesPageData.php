<?php

namespace App\Domain\Purchasing\Queries;

use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Models\Game;
use App\Models\JobRole;

class PurchasesPageData
{
    public function __construct(private readonly InventoryCapacityService $inventoryCapacity) {}

    public function for(Game $game): array
    {
        $game->load([
            'company.suppliers.products.product',
            'company.purchaseOrders.supplier',
            'company.purchaseOrders.items.product',
            'company.purchaseOrders.financialEntry',
            'company.purchaseOrders.employee',
        ]);

        $company = $game->company;
        $capacity = $this->inventoryCapacity->calculate($company);
        $purchasingCapacities = JobRole::where('department', 'Compras')->pluck('purchasing_capacity_units', 'name');
        $buyers = $company->employees
            ->where('status', 'active')
            ->where('department', 'Compras')
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role,
                'capacityUnits' => (int) ($purchasingCapacities[$employee->role] ?? 0),
            ])->values();

        return [
            'game' => [
                'id' => $game->id,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
                'victoryDays' => config('game.victory.days'),
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'cashBalanceCents' => $company->cash_balance_cents,
            'automaticPurchasing' => [
                'reorderPointDays' => config('game.purchasing.reorder_point_days'),
                'targetStockDays' => config('game.purchasing.target_stock_days'),
                'totalCapacityUnits' => (int) $buyers->sum('capacityUnits'),
                'buyers' => $buyers,
            ],
            'inventoryCapacity' => [
                'capacityUnits' => $capacity['capacity_units'],
                'stockUnits' => $capacity['stock_units'],
                'incomingUnits' => $capacity['incoming_units'],
                'usedUnits' => $capacity['used_units'],
                'availableUnits' => $capacity['available_units'],
            ],
            'suppliers' => $company->suppliers->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'profile' => $supplier->profile,
                'leadTimeDays' => $supplier->lead_time_days,
                'paymentTermDays' => $supplier->payment_term_days,
                'reliabilityPercent' => $supplier->reliability_percent,
                'offers' => $supplier->products->map(fn ($offer) => [
                    'productId' => $offer->product_id,
                    'productName' => $offer->product->name,
                    'sku' => $offer->product->sku,
                    'costCents' => $offer->cost_cents,
                    'minimumQuantity' => $offer->minimum_quantity,
                ])->values(),
            ])->values(),
            'orders' => $company->purchaseOrders
                ->sortByDesc('id')
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'supplierName' => $order->supplier->name,
                    'status' => $order->status,
                    'automatic' => $order->automatic,
                    'buyerName' => $order->employee?->name,
                    'orderedDate' => $order->ordered_at_game_date->toDateString(),
                    'expectedDeliveryDate' => $order->expected_delivery_date->toDateString(),
                    'receivedDate' => $order->received_at_game_date?->toDateString(),
                    'totalCents' => $order->total_cents,
                    'paymentStatus' => $order->status === 'cancelled'
                        ? 'cancelled'
                        : ($order->financialEntry?->paid_at ? 'paid' : 'payable'),
                    'dueDate' => $order->financialEntry?->due_date?->toDateString(),
                    'canReceive' => $order->status === 'ordered' && $game->current_date->greaterThanOrEqualTo($order->expected_delivery_date),
                    'canCancel' => $order->status === 'ordered',
                    'cancellationFeeCents' => intdiv($order->total_cents + 5, 10),
                    'items' => $order->items->map(fn ($item) => [
                        'productName' => $item->product->name,
                        'quantity' => $item->quantity,
                        'unitCostCents' => $item->unit_cost_cents,
                        'totalCents' => $item->total_cents,
                    ]),
                ])->values(),
        ];
    }
}
