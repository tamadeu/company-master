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
            'company.sales.items.customerPurchases',
            'company.sales.items.attributions.employee',
            'dailySnapshots',
        ]);
        $company = $game->company;
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
                'processedSales' => $company->sales->count(),
                'customerPurchases' => (int) $company->sales
                    ->flatMap(fn ($sale) => $sale->items)
                    ->sum(fn ($item) => $item->customerPurchases->count()),
            ],
            'sales' => $company->sales->sortByDesc('game_date')->map(fn ($sale) => [
                'id' => $sale->id,
                'date' => $sale->game_date->toDateString(),
                'revenueCents' => $sale->revenue_cents,
                'cogsCents' => $sale->cogs_cents,
                'grossProfitCents' => $sale->revenue_cents - $sale->cogs_cents,
                'unitsSold' => (int) $sale->items->sum('quantity'),
                'customerCount' => $sale->items
                    ->flatMap(fn ($item) => $item->customerPurchases)
                    ->pluck('customer_id')
                    ->unique()
                    ->count(),
                'items' => $sale->items->map(fn ($item) => [
                    'productName' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unitPriceCents' => $item->unit_price_cents,
                    'revenueCents' => $item->revenue_cents,
                    'cogsCents' => $item->cogs_cents,
                    'customerCount' => $item->customerPurchases->pluck('customer_id')->unique()->count(),
                    'sellers' => $item->attributions->map(
                        fn ($attribution) => $attribution->employee?->name ?? 'Gestor',
                    )->unique()->values(),
                ])->values(),
            ])->values(),
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
