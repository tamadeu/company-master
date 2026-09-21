<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated user creates a game with validated server data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('games.store'), [
        'company_name' => 'Mercado Aurora',
        'office_location' => 'premium',
        'cash_balance_cents' => 999_999_999,
    ]);

    $game = $user->games()->firstOrFail();

    $response->assertRedirect(route('games.show', $game));
    expect($game->name)->toBe("Partida #{$game->id}")
        ->and($game->company->cash_balance_cents)->toBe(10_000_000)
        ->and($game->company->settings['office_location'])->toBe('premium')
        ->and($game->company->financialEntries()->where('description', 'Aluguel')->value('amount_cents'))->toBe(1_500_000);
});

test('a user cannot access another users game', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Partida privada', 'Empresa privada', 123);

    $this->actingAs($intruder)
        ->get(route('games.show', $game))
        ->assertForbidden();
});

test('company onboarding rejects an unknown office location', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.store'), [
        'company_name' => 'Empresa inválida',
        'office_location' => 'lua',
    ])->assertSessionHasErrors('office_location');

    expect($user->games()->count())->toBe(0);
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
