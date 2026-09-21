<?php

namespace App\Domain\Sales\Services;

use App\Domain\Game\Services\EventEngine;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class IdleSalesProcessor
{
    public function __construct(
        private readonly SalesSimulator $salesSimulator,
        private readonly EventEngine $eventEngine,
    ) {}

    public function process(int $gameId, string $expectedGameDate, string $tickKey, int $progressBasisPoints): array
    {
        return DB::transaction(function () use ($gameId, $expectedGameDate, $tickKey, $progressBasisPoints) {
            $game = Game::query()->lockForUpdate()->find($gameId);
            if (! $game
                || $game->status !== 'active'
                || ! $game->automation_enabled
                || $game->current_date->toDateString() !== $expectedGameDate) {
                return $this->emptyResult();
            }

            $company = $game->company()->lockForUpdate()->firstOrFail();
            $existing = $company->sales()->where('tick_key', $tickKey)->first();
            if ($existing) {
                return $this->saleResult($existing);
            }

            $game->setRelation('company', $company);
            $eventEffects = $this->eventEngine->activeEffects($game);
            $seed = hexdec(substr(hash('sha256', "{$game->seed}:{$expectedGameDate}"), 0, 8));

            return $this->salesSimulator->simulate($game, $seed, $eventEffects, [
                'key' => $tickKey,
                'progress_basis_points' => max(0, min(10_000, $progressBasisPoints)),
            ]);
        }, attempts: 3);
    }

    private function saleResult($sale): array
    {
        return [
            'sale_id' => $sale->id,
            'revenue_cents' => $sale->revenue_cents,
            'cogs_cents' => $sale->cogs_cents,
            'units_sold' => (int) $sale->items()->sum('quantity'),
            'stockout_product_ids' => $sale->stockout_product_ids ?? [],
            'commercial_capacity_units' => $sale->commercial_capacity_units,
            'unmet_demand_units' => $sale->unmet_demand_units,
            'new_customers' => $sale->new_customers,
            'customer_purchases' => $sale->customer_purchases,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'sale_id' => 0,
            'revenue_cents' => 0,
            'cogs_cents' => 0,
            'units_sold' => 0,
            'stockout_product_ids' => [],
            'commercial_capacity_units' => 0,
            'unmet_demand_units' => 0,
            'new_customers' => 0,
            'customer_purchases' => 0,
        ];
    }
}
