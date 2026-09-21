<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\Customer;
use App\Models\CustomerPurchase;
use App\Models\CustomerOrder;
use App\Models\Game;
use App\Models\PopulationNpc;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function customerGame(int $seed = 980): Game
{
    return app(CreateGame::class)->execute(User::factory()->create(), 'Clientes', 'Mercado Aurora', $seed);
}

test('all games share one deterministic global population without sensitive columns', function () {
    $firstPopulation = PopulationNpc::query()->orderBy('code')->get(['id', 'code', 'name', 'birth_date', 'city', 'state'])->toArray();
    customerGame(1234);
    customerGame(5678);
    $populationAfterGames = PopulationNpc::query()->orderBy('code')->get(['id', 'code', 'name', 'birth_date', 'city', 'state'])->toArray();

    expect($firstPopulation)->toHaveCount(100)
        ->and($populationAfterGames)->toBe($firstPopulation)
        ->and(Schema::hasColumn('population_npcs', 'game_id'))->toBeFalse()
        ->and(Schema::hasColumn('population_npcs', 'cpf'))->toBeFalse()
        ->and(Schema::hasColumn('population_npcs', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('population_npcs', 'phone'))->toBeFalse();
});

test('every sold unit and revenue amount is linked to a customer purchase', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    Config::set('game.sales.owner_base_capacity_units', 100);
    $game = customerGame();
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['units_sold'])->toBeGreaterThan(0)
        ->and(CustomerPurchase::sum('quantity'))->toBe(SaleItem::sum('quantity'))
        ->and(CustomerPurchase::sum('revenue_cents'))->toBe(SaleItem::sum('revenue_cents'))
        ->and(CustomerPurchase::whereNull('customer_id')->count())->toBe(0)
        ->and(CustomerPurchase::whereNull('customer_order_id')->count())->toBe(0)
        ->and(Customer::count())->toBe($summary['new_customers'])
        ->and(Customer::sum('purchase_count'))->toBe(CustomerOrder::count())
        ->and(CustomerOrder::sum('total_quantity'))->toBe(SaleItem::sum('quantity'))
        ->and(CustomerOrder::sum('revenue_cents'))->toBe(SaleItem::sum('revenue_cents'))
        ->and(CustomerOrder::withCount('purchases')->get()->max('purchases_count'))->toBeGreaterThan(1)
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

test('daily customer acquisition never loads the complete population', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    $game = customerGame();
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }

    $populationQueries = [];
    DB::listen(function ($query) use (&$populationQueries): void {
        if (str_contains($query->sql, 'population_npcs')) {
            $populationQueries[] = strtolower($query->sql);
        }
    });

    app(AdvanceDay::class)->execute($game, '2026-01-01');

    $unboundedQueries = collect($populationQueries)->reject(fn (string $sql) => str_contains($sql, 'limit 1')
        || str_contains($sql, 'min(')
        || str_contains($sql, 'max('));

    expect($populationQueries)->not->toBeEmpty()
        ->and($unboundedQueries)->toBeEmpty();
});
