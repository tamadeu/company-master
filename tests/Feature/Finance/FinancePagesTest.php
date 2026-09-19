<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\SaleItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner sees finance balances income statement and ledger', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Financeiro', 'Mercado Aurora', 710);

    $this->actingAs($user)
        ->get(route('games.finance.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Index')
            ->where('summary.cashBalanceCents', 10_000_000)
            ->where('incomeStatement.revenueCents', 0)
            ->where('incomeStatement.operatingExpensesCents', 0)
            ->where('cashFlow.0.balanceCents', 10_000_000)
            ->has('cashFlow', 1)
            ->has('entries', 4));
});

test('the owner sees daily and product reports', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Relatórios', 'Mercado Aurora', 711);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 30, 'average_cost_cents' => 1_000]);
    }
    app(AdvanceDay::class)->execute($game, '2026-01-01');
    $productsSold = SaleItem::query()->pluck('product_id')->unique()->count();

    $this->actingAs($user)
        ->get(route('games.reports.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('dailySnapshots', 1)
            ->has('productSales', $productsSold)
            ->has('cashFlow', 2));
});

test('financial pages reject another user', function (string $routeName) {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Privada', 712);

    $this->actingAs($intruder)->get(route($routeName, $game))->assertForbidden();
})->with(['games.finance.index', 'games.reports.index']);
