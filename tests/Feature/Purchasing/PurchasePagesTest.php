<?php

use App\Domain\Game\Actions\CreateGame;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner can view supplier offers and purchase orders', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Compras', 'Mercado Aurora', 300);

    $this->actingAs($user)
        ->get(route('games.purchases.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Index')
            ->has('suppliers', 3)
            ->has('suppliers.0.offers', 5)
            ->has('orders', 0));
});

test('a user cannot view another users purchasing data', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Empresa privada', 301);

    $this->actingAs($intruder)
        ->get(route('games.purchases.index', $game))
        ->assertForbidden();
});

test('the purchase endpoint rejects a supplier from another game', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Principal', 'Empresa principal', 302);
    $otherGame = app(CreateGame::class)->execute($otherUser, 'Outra', 'Outra empresa', 303);
    $foreignSupplier = $otherGame->company->suppliers()->firstOrFail();
    $product = $game->company->products()->firstOrFail();

    $this->actingAs($user)
        ->from(route('games.purchases.index', $game))
        ->post(route('games.purchase-orders.store', $game), [
            'supplier_id' => $foreignSupplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('supplier_id');

    expect($game->company->purchaseOrders()->count())->toBe(0);
});

test('the owner sees balances and immutable movements in inventory', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Estoque', 'Mercado Aurora', 304);
    $supplier = $game->company->suppliers()->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 4],
    ]);
    $game->update(['current_date' => $order->expected_delivery_date]);
    app(ReceivePurchaseOrder::class)->execute($game->fresh(), $order);

    $this->actingAs($user)
        ->get(route('games.inventory.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Index')
            ->where('summary.totalUnits', 4)
            ->has('balances', 5)
            ->has('balances.0.salePriceCents')
            ->has('balances.0.referencePriceCents')
            ->has('movements', 1));
});

test('a user cannot view another users inventory', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Empresa privada', 305);

    $this->actingAs($intruder)
        ->get(route('games.inventory.index', $game))
        ->assertForbidden();
});
