<?php

namespace App\Domain\Inventory\Queries;

use App\Models\Game;

class InventoryPageData
{
    public function for(Game $game): array
    {
        $game->load([
            'company.inventoryBalances.product',
            'company.inventoryMovements.product',
        ]);

        $company = $game->company;

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
            'summary' => [
                'totalUnits' => $company->inventoryBalances->sum('quantity'),
                'totalValueCents' => $company->inventoryBalances->sum(
                    fn ($balance) => $balance->quantity * $balance->average_cost_cents,
                ),
                'productsInStock' => $company->inventoryBalances->where('quantity', '>', 0)->count(),
            ],
            'balances' => $company->inventoryBalances->map(fn ($balance) => [
                'productId' => $balance->product_id,
                'productName' => $balance->product->name,
                'sku' => $balance->product->sku,
                'salePriceCents' => $balance->product->sale_price_cents,
                'referencePriceCents' => $balance->product->reference_price_cents,
                'baseDailyDemand' => $balance->product->base_daily_demand,
                'quantity' => $balance->quantity,
                'averageCostCents' => $balance->average_cost_cents,
                'totalValueCents' => $balance->quantity * $balance->average_cost_cents,
            ])->sortBy('productName')->values(),
            'movements' => $company->inventoryMovements
                ->sortByDesc('id')
                ->map(fn ($movement) => [
                    'id' => $movement->id,
                    'productName' => $movement->product->name,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'unitCostCents' => $movement->unit_cost_cents,
                    'totalCostCents' => $movement->total_cost_cents,
                    'gameDate' => $movement->game_date->toDateString(),
                    'referenceId' => $movement->reference_id,
                ])->values(),
        ];
    }
}
