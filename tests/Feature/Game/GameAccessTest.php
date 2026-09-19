<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated user creates a game with validated server data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('games.store'), [
        'name' => 'Minha empresa',
        'company_name' => 'Mercado Aurora',
        'cash_balance_cents' => 999_999_999,
    ]);

    $game = $user->games()->firstOrFail();

    $response->assertRedirect(route('games.show', $game));
    expect($game->company->cash_balance_cents)->toBe(10_000_000);
});

test('a user cannot access another users game', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Partida privada', 'Empresa privada', 123);

    $this->actingAs($intruder)
        ->get(route('games.show', $game))
        ->assertForbidden();
});

test('the owner sees dashboard data derived from the game', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Partida inicial', 'Mercado Aurora', 123);

    $this->actingAs($user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('game.id', $game->id)
            ->where('company.name', 'Mercado Aurora')
            ->where('metrics.cashBalanceCents', 10_000_000)
            ->where('metrics.inventoryValueCents', 0)
            ->where('metrics.payablesNextSevenDaysCents', 800_000)
            ->has('products', 5)
            ->has('suppliers', 3));
});
