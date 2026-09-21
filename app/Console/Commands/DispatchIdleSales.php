<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIdleSales;
use App\Models\Game;
use Illuminate\Console\Command;

class DispatchIdleSales extends Command
{
    protected $signature = 'sales:dispatch-idle';

    protected $description = 'Dispatch the current idle sales tick for active games';

    public function handle(): int
    {
        if (! config('game.idle_sales.enabled')) {
            return self::SUCCESS;
        }

        $interval = max(1, config('game.idle_sales.interval_minutes'));
        $minuteOfDay = ((int) now()->format('H') * 60) + (int) now()->format('i');
        $slot = intdiv($minuteOfDay, $interval);
        $slotsPerDay = (int) ceil(1_440 / $interval);
        $progress = min(10_000, intdiv(($slot + 1) * 10_000, $slotsPerDay));
        $tickKey = now()->format('Y-m-d').'-'.str_pad((string) $slot, 4, '0', STR_PAD_LEFT);
        $dispatched = 0;

        Game::query()
            ->where('status', 'active')
            ->where('automation_enabled', true)
            ->where('next_processing_at', '>', now())
            ->whereDoesntHave('company.sales', fn ($query) => $query->where('tick_key', $tickKey))
            ->orderBy('id')
            ->limit(config('game.idle_sales.dispatch_batch_size'))
            ->get(['id', 'current_date'])
            ->each(function (Game $game) use (&$dispatched, $tickKey, $progress): void {
                ProcessIdleSales::dispatch($game->id, $game->current_date->toDateString(), $tickKey, $progress);
                $dispatched++;
            });

        $this->info("{$dispatched} partida(s) enviada(s) para vendas idle no tick {$tickKey}.");

        return self::SUCCESS;
    }
}
