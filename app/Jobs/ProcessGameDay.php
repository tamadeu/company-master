<?php

namespace App\Jobs;

use App\Domain\Game\Actions\AdvanceDay;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessGameDay implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $gameId) {}

    public function handle(AdvanceDay $advanceDay): void
    {
        $game = Game::query()->find($this->gameId);

        if (! $game
            || $game->status !== 'active'
            || ! $game->automation_enabled
            || ! $game->next_processing_at
            || $game->next_processing_at->isFuture()) {
            return;
        }

        $advanceDay->execute($game, $game->current_date->toDateString());
    }

    public function uniqueId(): string
    {
        return "game-day:{$this->gameId}";
    }
}
