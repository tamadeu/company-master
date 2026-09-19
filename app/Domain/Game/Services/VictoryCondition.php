<?php

namespace App\Domain\Game\Services;

use App\Models\Game;

class VictoryCondition
{
    public function isMet(Game $game, int $completedDays): bool
    {
        return $completedDays >= config('game.victory.days')
            && $this->equityCents($game) >= config('game.victory.equity_cents');
    }

    public function equityCents(Game $game): int
    {
        $company = $game->company;
        $inventoryValueCents = (int) $company->inventoryBalances()->get()->sum(
            fn ($balance) => $balance->quantity * $balance->average_cost_cents,
        );
        $receivablesCents = (int) $company->financialEntries()->where('type', 'inflow')->whereNull('paid_at')->sum('amount_cents');
        $payablesCents = (int) $company->financialEntries()->where('type', 'outflow')->whereNull('paid_at')->sum('amount_cents');

        return $company->cash_balance_cents + $receivablesCents + $inventoryValueCents - $payablesCents;
    }
}
