<?php

namespace App\Domain\Game\Actions;

use App\Models\Game;

class CompleteTutorial
{
    public function execute(Game $game): void
    {
        $company = $game->company;
        $company->update([
            'settings' => [
                ...($company->settings ?? []),
                'tutorial_completed' => true,
            ],
        ]);
    }
}
