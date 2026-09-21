<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Illuminate\Support\Facades\Config;

test('office location defines rent and is stored as a company snapshot', function (string $location, int $rentCents, int $demandFactor) {
    $game = app(CreateGame::class)->execute(
        User::factory()->create(),
        'Localização',
        'Empresa',
        8100,
        $location,
    );

    expect($game->company->settings)->toMatchArray([
        'office_location' => $location,
        'office_rent_cents' => $rentCents,
        'office_demand_factor_basis_points' => $demandFactor,
    ])->and($game->company->financialEntries()->where('description', 'Aluguel')->value('amount_cents'))->toBe($rentCents);
})->with([
    'bairro' => ['neighborhood', 400_000, 8_500],
    'centro' => ['downtown', 800_000, 10_000],
    'premium' => ['premium', 1_500_000, 12_500],
]);

test('a premium office produces more demand than a low cost office with the same seed', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    Config::set('game.sales.owner_base_capacity_units', 100);
    $economy = app(CreateGame::class)->execute(User::factory()->create(), 'Econômica', 'Empresa A', 8101, 'neighborhood');
    $premium = app(CreateGame::class)->execute(User::factory()->create(), 'Premium', 'Empresa B', 8101, 'premium');

    foreach ([$economy, $premium] as $game) {
        foreach ($game->company->inventoryBalances as $balance) {
            $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
        }
    }

    $economySummary = app(AdvanceDay::class)->execute($economy, '2026-01-01');
    $premiumSummary = app(AdvanceDay::class)->execute($premium, '2026-01-01');

    expect($premiumSummary['units_sold'])->toBeGreaterThan($economySummary['units_sold']);
});
