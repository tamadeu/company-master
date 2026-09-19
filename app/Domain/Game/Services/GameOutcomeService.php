<?php

namespace App\Domain\Game\Services;

use App\Models\Game;

class GameOutcomeService
{
    public function __construct(
        private readonly BankruptcyCondition $bankruptcy,
        private readonly VictoryCondition $victory,
    ) {}

    /** @return array{status: string, equity_cents: int} */
    public function evaluate(Game $game, int $completedDays): array
    {
        $status = 'active';

        if ($this->bankruptcy->isMet($game)) {
            $status = 'bankrupt';
        } elseif ($this->victory->isMet($game, $completedDays)) {
            $status = 'won';
        }

        if ($status !== 'active') {
            $game->update(['status' => $status, 'ended_at' => now()]);
        }

        return [
            'status' => $status,
            'equity_cents' => $this->victory->equityCents($game),
        ];
    }
}
