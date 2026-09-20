<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner sees population before any customer conversion', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Clientes', 'Mercado Aurora', 990);

    $this->actingAs($user)
        ->get(route('games.customers.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->where('summary.populationCount', 100)
            ->where('summary.customerCount', 0)
            ->has('prospects', 20)
            ->has('purchases', 0));
});

test('the customer page reflects automatic conversion and purchases', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Clientes', 'Mercado Aurora', 991);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }
    app(AdvanceDay::class)->execute($game, '2026-01-01');
    $customerCount = $game->company->customers()->count();

    $this->actingAs($user)
        ->get(route('games.customers.index', $game))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.customerCount', $customerCount)
            ->where('summary.purchaseCount', $customerCount)
            ->has('customers', $customerCount)
            ->has('purchases', $customerCount));
});

test('another user cannot access customer or population data', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Privada', 992);

    $this->actingAs($intruder)
        ->get(route('games.customers.index', $game))
        ->assertForbidden();
});
