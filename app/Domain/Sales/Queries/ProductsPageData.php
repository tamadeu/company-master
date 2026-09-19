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
            'company.products.inventoryBalances',
            'company.products.saleItems',
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
            'products' => $company->products->map(fn ($product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'salePriceCents' => $product->sale_price_cents,
                'referencePriceCents' => $product->reference_price_cents,
                'baseDailyDemand' => $product->base_daily_demand,
                'stockQuantity' => $product->inventoryBalances->first()?->quantity ?? 0,
                'unitsSold' => $product->saleItems->sum('quantity'),
                'revenueCents' => $product->saleItems->sum('revenue_cents'),
            ])->values(),
        ];
    }
}
