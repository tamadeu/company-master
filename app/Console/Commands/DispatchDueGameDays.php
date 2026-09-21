<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGameDay;
use App\Models\Game;
use Illuminate\Console\Command;

class DispatchDueGameDays extends Command
{
    protected $signature = 'games:dispatch-due';

    protected $description = 'Dispatch queued daily processing for due active games';

    public function handle(): int
    {
        if (! config('game.automation.enabled')) {
            return self::SUCCESS;
        }

        $dispatched = 0;
        Game::query()
            ->where('status', 'active')
            ->where('automation_enabled', true)
            ->whereNotNull('next_processing_at')
            ->where('next_processing_at', '<=', now())
            ->orderBy('next_processing_at')
            ->limit(config('game.automation.dispatch_batch_size'))
            ->pluck('id')
            ->each(function (int $gameId) use (&$dispatched): void {
                ProcessGameDay::dispatch($gameId);
                $dispatched++;
            });

        $this->info("{$dispatched} partida(s) enviada(s) para processamento.");

        return self::SUCCESS;
    }
}
