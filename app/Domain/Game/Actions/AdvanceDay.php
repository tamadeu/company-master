<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Services\DayProcessor;
use App\Models\DayProcess;
use App\Models\Game;
use Throwable;

class AdvanceDay
{
    public function __construct(private readonly DayProcessor $dayProcessor) {}

    public function execute(Game $game, string $expectedDate): array
    {
        try {
            return $this->dayProcessor->process($game, $expectedDate);
        } catch (Throwable $exception) {
            DayProcess::query()->updateOrCreate(
                ['game_id' => $game->id, 'game_date' => $expectedDate],
                [
                    'status' => 'failed',
                    'seed_used' => $this->dayProcessor->daySeed($game->seed, $expectedDate),
                    'error' => mb_substr($exception->getMessage(), 0, 2_000),
                ],
            );

            throw $exception;
        }
    }
}
