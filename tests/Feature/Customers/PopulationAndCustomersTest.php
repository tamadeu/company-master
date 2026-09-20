<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\Customer;
use App\Models\CustomerPurchase;
use App\Models\Game;
use App\Models\PopulationNpc;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

function customerGame(int $seed = 980): Game
{
    return app(CreateGame::class)->execute(User::factory()->create(), 'Clientes', 'Mercado Aurora', $seed);
}

test('a game receives a deterministic fictional population without sensitive columns', function () {
    $firstGame = customerGame(1234);
    $firstPopulation = $firstGame->populationNpcs()->orderBy('code')->get(['code', 'name', 'birth_date', 'city', 'state'])->toArray();
    $secondGame = customerGame(1234);
    $secondPopulation = $secondGame->populationNpcs()->orderBy('code')->get(['code', 'name', 'birth_date', 'city', 'state'])->toArray();

    expect($firstPopulation)->toHaveCount(100)
        ->and($secondPopulation)->toBe($firstPopulation)
        ->and(Schema::hasColumn('population_npcs', 'cpf'))->toBeFalse()
        ->and(Schema::hasColumn('population_npcs', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('population_npcs', 'phone'))->toBeFalse();
});

test('every sold unit and revenue amount is linked to a customer purchase', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    $game = customerGame();
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['units_sold'])->toBeGreaterThan(0)
        ->and(CustomerPurchase::sum('quantity'))->toBe(SaleItem::sum('quantity'))
        ->and(CustomerPurchase::sum('revenue_cents'))->toBe(SaleItem::sum('revenue_cents'))
        ->and(CustomerPurchase::whereNull('customer_id')->count())->toBe(0)
        ->and(Customer::count())->toBe($summary['new_customers'])
        ->and(Customer::sum('purchase_count'))->toBe(CustomerPurchase::count())
        ->and(Customer::sum('lifetime_value_cents'))->toBe(CustomerPurchase::sum('revenue_cents'));
});

test('population members only become customers after a purchase', function () {
    $game = customerGame();

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect(PopulationNpc::count())->toBe(100)
        ->and($summary['units_sold'])->toBe(0)
        ->and(Customer::count())->toBe(0)
        ->and(CustomerPurchase::count())->toBe(0);
});
