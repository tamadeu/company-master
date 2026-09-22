<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated user creates a game with validated server data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('games.store'), [
        'company_name' => 'Mercado Aurora',
        'office_location' => 'premium',
        'difficulty' => 'normal',
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
        'difficulty' => 'normal',
    ])->assertSessionHasErrors('office_location');

    expect($user->games()->count())->toBe(0);
});

test('company onboarding rejects an unknown difficulty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.store'), [
        'company_name' => 'Empresa inválida',
        'office_location' => 'downtown',
        'difficulty' => 'impossível',
    ])->assertSessionHasErrors('difficulty');

    expect($user->games()->count())->toBe(0);
});

test('the owner sees dashboard data derived from the game', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Partida inicial', 'Mercado Aurora', 123);
    $company = $game->company;
    $product = $company->products()->firstOrFail();
    $supplier = $company->suppliers()->firstOrFail();
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'expected_delivery_date' => '2026-01-03',
        'total_cents' => 42_000,
    ]);
    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 12,
        'unit_cost_cents' => 3_500,
        'total_cents' => 42_000,
    ]);
    $sale = Sale::query()->create([
        'company_id' => $company->id,
        'game_date' => $game->current_date,
        'status' => 'completed',
        'revenue_cents' => 57_000,
        'cogs_cents' => 30_000,
    ]);
    SaleItem::query()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price_cents' => 5_700,
        'unit_cost_cents' => 3_000,
        'revenue_cents' => 57_000,
        'cogs_cents' => 30_000,
    ]);

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
            ->has('suppliers', 3)
            ->where('upcomingDeliveries.0.supplierName', $supplier->name)
            ->where('upcomingDeliveries.0.expectedDeliveryDate', '2026-01-03')
            ->where('upcomingDeliveries.0.units', 12)
            ->where('upcomingDeliveries.0.totalCents', 42_000)
            ->where('salesByProduct.0.productName', $product->name)
            ->where('salesByProduct.0.revenueCents', 57_000)
            ->where('salesByProduct.0.unitsSold', 10)
            ->where('operationalAlerts.0.type', 'stockout')
            ->where('operationalAlerts.0.severity', 'critical')
            ->where('operationalAlerts.0.title', "Ruptura: {$product->name}")
            ->where('operationalAlerts.0.area', 'inventory'));
});
