<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Sales\Services\IdleSalesProcessor;
use App\Domain\Sales\Services\SalesCapacityService;
use App\Jobs\ProcessIdleSales;
use App\Models\DailySnapshot;
use App\Models\Game;
use App\Models\InboxMessage;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

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
    $notification = InboxMessage::where('recipient_user_id', $game->user_id)->where('category', 'sale')->firstOrFail();
    expect($notification->metadata)->toMatchArray([
        'sale_id' => $result['sale_id'],
        'tick_key' => '2026-09-21-0012',
        'game_id' => $game->id,
        'game_date' => '2026-01-01',
        'revenue_cents' => $result['revenue_cents'],
        'units_sold' => $result['units_sold'],
    ])->and($notification->action_url)->toBe("/games/{$game->id}/products");
});

test('the same idle tick is idempotent', function () {
    $this->app->instance('env', 'production');
    $game = idleSalesGame(8301);
    $processor = app(IdleSalesProcessor::class);

    $first = $processor->process($game->id, '2026-01-01', 'tick-1', 5_000);
    $second = $processor->process($game->id, '2026-01-01', 'tick-1', 5_000);

    expect($second)->toBe($first)
        ->and(Sale::where('company_id', $game->company->id)->count())->toBe(1)
        ->and(InboxMessage::where('recipient_user_id', $game->user_id)->where('category', 'sale')->count())->toBe(1);
});

test('an idle tick without sold units does not create a notification', function () {
    $this->app->instance('env', 'production');
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Sem estoque', 'Empresa sem estoque', 8306);

    $result = app(IdleSalesProcessor::class)->process($game->id, '2026-01-01', 'empty-tick', 5_000);

    expect($result['units_sold'])->toBe(0)
        ->and(InboxMessage::where('recipient_user_id', $game->user_id)->where('category', 'sale')->exists())->toBeFalse();
});

test('an idle sale appears in both inbox and sales notifications', function () {
    $this->app->instance('env', 'production');
    $game = idleSalesGame(8307);
    app(IdleSalesProcessor::class)->process($game->id, '2026-01-01', 'visible-tick', 10_000);
    $message = InboxMessage::where('recipient_user_id', $game->user_id)->where('category', 'sale')->firstOrFail();

    $this->actingAs($game->user)
        ->get(route('inbox.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.data.0.id', $message->id)
            ->where('messages.data.0.category', 'sale'));

    $this->actingAs($game->user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.id', $message->id)
            ->where('notifications.data.0.metadata.tick_key', 'visible-tick'));
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
        ->and(DailySnapshot::value('sales_revenue_cents'))->toBe($idle['revenue_cents'])
        ->and(InboxMessage::where('recipient_user_id', $game->user_id)->where('category', 'sale')->count())->toBe(1);
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
