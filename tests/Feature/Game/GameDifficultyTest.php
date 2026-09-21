<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\User;

test('difficulty defines initial capital and is stored as a company snapshot', function (string $difficulty, int $capitalCents, string $name) {
    $game = app(CreateGame::class)->execute(
        User::factory()->create(),
        'Dificuldade',
        'Empresa',
        8200,
        'downtown',
        $difficulty,
    );

    expect($game->company->cash_balance_cents)->toBe($capitalCents)
        ->and($game->company->settings)->toMatchArray([
            'difficulty' => $difficulty,
            'difficulty_name' => $name,
            'initial_capital_cents' => $capitalCents,
        ])
        ->and($game->company->financialEntries()->where('category', 'initial_capital')->value('amount_cents'))->toBe($capitalCents);
})->with([
    'fácil' => ['easy', 20_000_000, 'Fácil'],
    'normal' => ['normal', 10_000_000, 'Normal'],
    'desafiador' => ['challenging', 5_000_000, 'Desafiador'],
]);
