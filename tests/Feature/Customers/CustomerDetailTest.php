<?php

use App\Domain\Customers\Queries\CustomerDetailData;
use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

function gameWithCustomer(int $seed = 995): array
{
    Config::set('game.events.daily_chance_basis_points', 0);
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Analytics', 'Mercado Aurora', $seed);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }
    app(AdvanceDay::class)->execute($game, '2026-01-01');

    return [$user, $game, $game->company->customers()->firstOrFail()];
}

test('the owner sees customer profile and purchase analytics', function () {
    [$user, $game, $customer] = gameWithCustomer();

    $this->actingAs($user)
        ->get(route('games.customers.show', [$game, $customer]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Show')
            ->where('customer.id', $customer->id)
            ->where('analytics.purchaseCount', $customer->purchase_count)
            ->where('analytics.lifetimeValueCents', $customer->lifetime_value_cents)
            ->has('products', 1)
            ->has('timeline', 1)
            ->has('purchases', $customer->purchase_count));
});

test('customer analytics totals match the customer ledger', function () {
    [, $game, $customer] = gameWithCustomer(996);
    $data = app(CustomerDetailData::class)->for($game, $customer);

    expect(collect($data['products'])->sum('revenueCents'))->toBe($customer->lifetime_value_cents)
        ->and(collect($data['purchases'])->sum('quantity'))->toBe($data['analytics']['totalUnits'])
        ->and(collect($data['purchases'])->sum('revenueCents'))->toBe($data['analytics']['lifetimeValueCents']);
});

test('another user cannot view the customer detail', function () {
    [, $game, $customer] = gameWithCustomer(997);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('games.customers.show', [$game, $customer]))
        ->assertForbidden();
});

test('a customer from another company is not exposed', function () {
    [$user, $game] = gameWithCustomer(998);
    [, , $foreignCustomer] = gameWithCustomer(999);

    $this->actingAs($user)
        ->get(route('games.customers.show', [$game, $foreignCustomer]))
        ->assertNotFound();
});
