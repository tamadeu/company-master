<?php

namespace App\Domain\Sales\Queries;

use App\Domain\Sales\Services\SalesCapacityService;
use App\Models\Game;

class ProductsPageData
{
    public function __construct(private readonly SalesCapacityService $salesCapacity) {}

    public function for(Game $game): array
    {
        $game->load([
            'company.sales.items.product',
            'company.sales.items.attributions.employee',
            'dailySnapshots',
        ]);
        $company = $game->company;
        $orders = $company->customerOrders()
            ->with(['customer.populationNpc', 'purchases.saleItem.product'])
            ->orderByDesc('game_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $daySeed = hexdec(substr(hash('sha256', "{$game->seed}:{$game->current_date->toDateString()}"), 0, 8));
        $capacity = $this->salesCapacity->calculate($game, $daySeed);
        $latestSnapshot = $game->dailySnapshots->sortByDesc('game_date')->first();

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
            'salesRules' => [
                'dailyCapacityUnits' => $capacity['total_units'],
                'commercialEmployees' => $capacity['commercial_employee_count'],
                'ownerBaseCapacityUnits' => config('game.sales.owner_base_capacity_units'),
                'productivityMinBasisPoints' => config('game.sales.productivity_min_basis_points'),
                'productivityMaxBasisPoints' => config('game.sales.productivity_max_basis_points'),
                'latestUnmetDemandUnits' => $latestSnapshot?->summary['unmet_demand_units'] ?? 0,
            ],
            'summary' => [
                'revenueCents' => (int) $company->sales->sum('revenue_cents'),
                'cogsCents' => (int) $company->sales->sum('cogs_cents'),
                'unitsSold' => (int) $company->sales->flatMap(fn ($sale) => $sale->items)->sum('quantity'),
                'processedSales' => $orders->total(),
                'customerPurchases' => $orders->total(),
            ],
            'orders' => $orders->through(fn ($order) => [
                'id' => $order->id,
                'number' => 'VEN-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
                'date' => $order->game_date->toDateString(),
                'status' => $order->status,
                'customer' => [
                    'id' => $order->customer->id,
                    'name' => $order->customer->populationNpc->name,
                    'code' => $order->customer->populationNpc->code,
                ],
                'skuCount' => $order->purchases->pluck('saleItem.product_id')->unique()->count(),
                'totalQuantity' => $order->total_quantity,
                'revenueCents' => $order->revenue_cents,
                'items' => $order->purchases->sortBy('saleItem.product.name')->map(fn ($purchase) => [
                    'productId' => $purchase->saleItem->product_id,
                    'productName' => $purchase->saleItem->product->name,
                    'sku' => $purchase->saleItem->product->sku,
                    'quantity' => $purchase->quantity,
                    'unitPriceCents' => $purchase->saleItem->unit_price_cents,
                    'revenueCents' => $purchase->revenue_cents,
                ])->values(),
            ]),
            'productPerformance' => $company->sales
                ->flatMap(fn ($sale) => $sale->items)
                ->groupBy('product_id')
                ->map(fn ($items) => [
                    'productId' => $items->first()->product_id,
                    'productName' => $items->first()->product->name,
                    'unitsSold' => (int) $items->sum('quantity'),
                    'revenueCents' => (int) $items->sum('revenue_cents'),
                    'grossProfitCents' => (int) $items->sum('revenue_cents') - (int) $items->sum('cogs_cents'),
                ])->sortByDesc('revenueCents')->values(),
        ];
    }
}
