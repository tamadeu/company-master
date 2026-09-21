<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Sales\Services\IdleSalesProcessor;
use App\Domain\Sales\Services\SalesCapacityService;
use App\Jobs\ProcessIdleSales;
use App\Models\DailySnapshot;
use App\Models\Game;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;

function idleSalesGame(int $seed = 8300): Game
{
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Idle', 'Empresa Idle', $seed);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }

    return $game->fresh('company');
}

test('an idle sales tick sells without advancing the game date or requiring authentication', function () {
    CarbonImmutable::setTestNow('2026-09-21 12:00:00');
    $this->app->instance('env', 'production');
    $game = idleSalesGame();
    $cashBefore = $game->company->cash_balance_cents;

    $result = app(IdleSalesProcessor::class)->process($game->id, '2026-01-01', '2026-09-21-0012', 5_000);

    expect(auth()->check())->toBeFalse()
        ->and($result['units_sold'])->toBeGreaterThan(0)
        ->and($game->fresh()->current_date->toDateString())->toBe('2026-01-01')
        ->and($game->company->fresh()->cash_balance_cents)->toBeGreaterThan($cashBefore)
        ->and(Sale::where('tick_key', '2026-09-21-0012')->exists())->toBeTrue();
});

test('the same idle tick is idempotent', function () {
    $this->app->instance('env', 'production');
    $game = idleSalesGame(8301);
    $processor = app(IdleSalesProcessor::class);

    $first = $processor->process($game->id, '2026-01-01', 'tick-1', 5_000);
    $second = $processor->process($game->id, '2026-01-01', 'tick-1', 5_000);

    expect($second)->toBe($first)
        ->and(Sale::where('company_id', $game->company->id)->count())->toBe(1);
});

test('successive ticks respect cumulative daily commercial capacity', function () {
    $this->app->instance('env', 'production');
    $game = idleSalesGame(8302);
    $processor = app(IdleSalesProcessor::class);
    $daySeed = hexdec(substr(hash('sha256', "{$game->seed}:2026-01-01"), 0, 8));
    $capacity = app(SalesCapacityService::class)->calculate($game, $daySeed)['total_units'];

    $processor->process($game->id, '2026-01-01', 'tick-25', 2_500);
    $processor->process($game->id, '2026-01-01', 'tick-50', 5_000);
    $processor->process($game->id, '2026-01-01', 'tick-100', 10_000);

    $units = (int) Sale::where('company_id', $game->company->id)->with('items')->get()->flatMap->items->sum('quantity');
    expect($units)->toBeLessThanOrEqual($capacity)
        ->and(Sale::count())->toBe(3);
});

test('production midnight closing aggregates idle sales without creating another sale', function () {
    CarbonImmutable::setTestNow('2026-09-21 23:00:00');
    $this->app->instance('env', 'production');
    $game = idleSalesGame(8303);
    $idle = app(IdleSalesProcessor::class)->process($game->id, '2026-01-01', 'final-tick', 10_000);

    $summary = app(AdvanceDay::class)->execute($game->fresh(), '2026-01-01');

    expect(Sale::where('company_id', $game->company->id)->count())->toBe(1)
        ->and($summary['units_sold'])->toBe($idle['units_sold'])
        ->and($summary['sales_revenue_cents'])->toBe($idle['revenue_cents'])
        ->and($game->fresh()->current_date->toDateString())->toBe('2026-01-02')
        ->and(DailySnapshot::value('sales_revenue_cents'))->toBe($idle['revenue_cents']);
});

test('the idle dispatcher queues active games before their midnight closing', function () {
    CarbonImmutable::setTestNow('2026-09-21 12:00:00');
    $this->app->instance('env', 'production');
    Bus::fake();
    $game = idleSalesGame(8304);

    $this->artisan('sales:dispatch-idle')
        ->expectsOutputToContain('1 partida(s) enviada(s) para vendas idle')
        ->assertSuccessful();

    Bus::assertDispatched(ProcessIdleSales::class, fn (ProcessIdleSales $job) => $job->gameId === $game->id
        && $job->gameDate === '2026-01-01'
        && $job->progressBasisPoints === 5_416);
});

test('the dispatcher skips games that already processed the current tick', function () {
    CarbonImmutable::setTestNow('2026-09-21 12:00:00');
    $this->app->instance('env', 'production');
    Bus::fake();
    $game = idleSalesGame(8305);
    app(IdleSalesProcessor::class)->process($game->id, '2026-01-01', '2026-09-21-0012', 5_416);

    $this->artisan('sales:dispatch-idle')
        ->expectsOutput('0 partida(s) enviada(s) para vendas idle no tick 2026-09-21-0012.')
        ->assertSuccessful();

    Bus::assertNotDispatched(ProcessIdleSales::class);
});
