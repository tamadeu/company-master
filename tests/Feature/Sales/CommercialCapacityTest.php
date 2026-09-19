<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Game\Services\DayProcessor;
use App\Domain\Hr\Actions\HireEmployee;
use App\Domain\Hr\Actions\TerminateEmployee;
use App\Domain\Sales\Services\SalesCapacityService;
use App\Models\Game;
use App\Models\SaleAttribution;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Config;

function commercialGame(int $seed = 970): Game
{
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Comercial', 'Mercado Aurora', $seed);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }

    return $game;
}

test('stock abundance is limited by the owners base sales capacity', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    $game = commercialGame();

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['commercial_capacity_units'])->toBe(5)
        ->and($summary['units_sold'])->toBeLessThanOrEqual(5)
        ->and($game->company->inventoryBalances()->sum('quantity'))->toBeGreaterThanOrEqual(495)
        ->and(SaleAttribution::whereNull('employee_id')->sum('quantity'))->toBe($summary['units_sold']);
});

test('active commercial employees increase capacity and receive sales attribution', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    $game = commercialGame();
    $employee = app(HireEmployee::class)->execute($game, [
        'name' => 'Ana Comercial', 'department' => 'Comercial', 'role' => 'Analista',
        'monthly_salary_cents' => 350_000,
    ]);
    $seedUsed = app(DayProcessor::class)->daySeed($game->seed, '2026-01-01');
    $capacity = app(SalesCapacityService::class)->calculate($game, $seedUsed);

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($capacity['commercial_employee_count'])->toBe(1)
        ->and($summary['commercial_capacity_units'])->toBe($capacity['total_units'])
        ->and($summary['units_sold'])->toBeLessThanOrEqual($capacity['total_units'])
        ->and(SaleAttribution::where('employee_id', $employee->id)->sum('quantity'))->toBeGreaterThan(0)
        ->and(SaleAttribution::sum('quantity'))->toBe(SaleItem::sum('quantity'))
        ->and(SaleAttribution::sum('revenue_cents'))->toBe(SaleItem::sum('revenue_cents'));
});

test('terminated and non commercial employees do not increase capacity', function () {
    $game = commercialGame();
    $commercial = app(HireEmployee::class)->execute($game, [
        'name' => 'Bruno', 'department' => 'Comercial', 'role' => 'Gerente', 'monthly_salary_cents' => 500_000,
    ]);
    app(HireEmployee::class)->execute($game, [
        'name' => 'Carla', 'department' => 'Operações', 'role' => 'Gerente', 'monthly_salary_cents' => 500_000,
    ]);
    app(TerminateEmployee::class)->execute($game, $commercial);

    $first = app(SalesCapacityService::class)->calculate($game, 12345);
    $second = app(SalesCapacityService::class)->calculate($game, 12345);

    expect($first)->toBe($second)
        ->and($first['commercial_employee_count'])->toBe(0)
        ->and($first['total_units'])->toBe(5);
});
