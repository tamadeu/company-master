<?php

namespace App\Domain\Game\Services;

use App\Models\Game;

class BankruptcyCondition
{
    public function isMet(Game $game): bool
    {
        return $game->company->financialEntries()
            ->where('type', 'outflow')
            ->whereNull('paid_at')
            ->whereDate('due_date', '<=', $game->current_date)
            ->exists();
    }
}
