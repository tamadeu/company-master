<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner updates a product price using integer cents', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Preços', 'Mercado Aurora', 600);
    $product = $game->company->products()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('games.products.update', [$game, $product]), ['sale_price_cents' => 4_321])
        ->assertRedirect(route('games.inventory.index', $game));

    expect($product->fresh()->sale_price_cents)->toBe(4_321);
});

test('a product from another game cannot be updated', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Principal', 'Principal', 601);
    $otherGame = app(CreateGame::class)->execute($otherUser, 'Outra', 'Outra', 602);
    $foreignProduct = $otherGame->company->products()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('games.products.update', [$game, $foreignProduct]), ['sale_price_cents' => 4_321])
        ->assertNotFound();
});

test('the sales page exposes sales records and commercial capacity', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Produtos', 'Mercado Aurora', 603);

    $this->actingAs($user)
        ->get(route('games.products.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('summary.revenueCents', 0)
            ->where('summary.unitsSold', 0)
            ->has('orders.data', 0)
            ->has('productPerformance', 0)
            ->missing('products')
            ->where('salesRules.dailyCapacityUnits', 5));
});

test('the sales page shows identified customer orders and cart items', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Vendas', 'Mercado Aurora', 604);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }
    app(AdvanceDay::class)->execute($game, '2026-01-01');

    $this->actingAs($user)
        ->get(route('games.products.index', $game))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data')
            ->where('orders.data.0.number', fn ($number) => str_starts_with($number, 'VEN-'))
            ->has('orders.data.0.customer.name')
            ->has('orders.data.0.items')
            ->has('productPerformance'));
});
