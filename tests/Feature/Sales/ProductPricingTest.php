<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner updates a product price using integer cents', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Preços', 'Mercado Aurora', 600);
    $product = $game->company->products()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('games.products.update', [$game, $product]), ['sale_price_cents' => 4_321])
        ->assertRedirect(route('games.products.index', $game));

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

test('the products page exposes stock prices and sales totals', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Produtos', 'Mercado Aurora', 603);

    $this->actingAs($user)
        ->get(route('games.products.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->has('products', 5)
            ->where('products.0.revenueCents', 0));
});
