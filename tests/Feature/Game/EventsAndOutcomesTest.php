<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Game\Services\EventEngine;
use App\Domain\Game\Services\VictoryCondition;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Models\FinancialSettlement;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;

function finalPhaseGame(int $seed = 800): Game
{
    return app(CreateGame::class)->execute(User::factory()->create(), 'Eventos', 'Mercado Aurora', $seed);
}

function forceSingleEvent(string $type, array $definition): void
{
    Config::set('game.events.daily_chance_basis_points', 10_000);
    Config::set('game.events.definitions', [
        $type => ['weight' => 1, ...$definition],
    ]);
}

test('a timed product event changes effects without mutating the product', function () {
    forceSingleEvent('trending_product', [
        'duration_days' => 5,
        'demand_factor_basis_points' => 12_000,
        'reference_price_factor_basis_points' => 12_000,
    ]);
    $game = finalPhaseGame();
    $product = $game->company->products()->firstOrFail();
    $originalReferencePrice = $product->reference_price_cents;

    $event = app(EventEngine::class)->trigger($game, 123);
    $game->update(['current_date' => $game->current_date->addDay()]);
    $effects = app(EventEngine::class)->activeEffects($game->fresh());

    expect($event->type)->toBe('trending_product')
        ->and($effects['products'][$event->payload['product_id']]['demand_factor'])->toBe(12_000)
        ->and($effects['products'][$event->payload['product_id']]['reference_price_factor'])->toBe(12_000)
        ->and($product->fresh()->reference_price_cents)->toBe($originalReferencePrice);
});

test('at most one event is recorded for the same game date', function () {
    forceSingleEvent('heavy_rain', ['duration_days' => 2, 'demand_factor_basis_points' => 8_500]);
    $game = finalPhaseGame();

    $first = app(EventEngine::class)->trigger($game, 1);
    $second = app(EventEngine::class)->trigger($game, 999);

    expect($second->id)->toBe($first->id)
        ->and(GameEvent::count())->toBe(1);
});

test('equivalent games with the same seed select the same event', function () {
    Config::set('game.events.daily_chance_basis_points', 10_000);
    Config::set('game.events.definitions', [
        'influencer_recommendation' => ['weight' => 25, 'duration_days' => 3, 'demand_factor_basis_points' => 16_000],
        'heavy_rain' => ['weight' => 25, 'duration_days' => 2, 'demand_factor_basis_points' => 8_500],
    ]);
    $firstGame = finalPhaseGame(810);
    $secondGame = finalPhaseGame(810);

    $first = app(EventEngine::class)->trigger($firstGame, 123456);
    $second = app(EventEngine::class)->trigger($secondGame, 123456);

    expect($second->type)->toBe($first->type)
        ->and($second->title)->toBe($first->title);
});

test('expired events stop affecting demand', function () {
    forceSingleEvent('heavy_rain', ['duration_days' => 2, 'demand_factor_basis_points' => 8_500]);
    $game = finalPhaseGame();
    $event = app(EventEngine::class)->trigger($game, 4);
    $game->update(['current_date' => $event->ends_on->addDay()]);

    $effects = app(EventEngine::class)->activeEffects($game->fresh());

    expect($effects['global_demand_factor'])->toBe(10_000)
        ->and($event->fresh()->status)->toBe('expired');
});

test('supplier delay moves an ordered delivery by two days', function () {
    forceSingleEvent('supplier_delay', ['delay_days' => 2]);
    $game = finalPhaseGame();
    $supplier = $game->company->suppliers()->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 1],
    ]);
    $originalDate = $order->expected_delivery_date;

    $event = app(EventEngine::class)->trigger($game, 2);

    expect($event->type)->toBe('supplier_delay')
        ->and($order->fresh()->expected_delivery_date->toDateString())->toBe($originalDate->addDays(2)->toDateString());
});

test('maintenance creates and settles an operating expense within configured bounds', function () {
    forceSingleEvent('emergency_maintenance', ['minimum_cents' => 50_000, 'maximum_cents' => 200_000]);
    $game = finalPhaseGame();
    $settlementsBefore = FinancialSettlement::count();

    $event = app(EventEngine::class)->trigger($game, 3);
    $entry = $game->company->financialEntries()->findOrFail($event->payload['financial_entry_id']);

    expect($event->payload['amount_cents'])->toBeBetween(50_000, 200_000)
        ->and($entry->category)->toBe('maintenance')
        ->and($entry->paid_at)->not->toBeNull()
        ->and(FinancialSettlement::count())->toBe($settlementsBefore + 1);
});

test('the game is won after the configured duration and target equity', function () {
    Config::set('game.events.daily_chance_basis_points', 0);
    Config::set('game.victory.days', 1);
    Config::set('game.victory.equity_cents', 1);
    $game = finalPhaseGame();

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['game_status'])->toBe('won')
        ->and($game->fresh()->status)->toBe('won')
        ->and($game->fresh()->ended_at)->not->toBeNull();
});

test('simplified equity includes cash receivables inventory and payables', function () {
    $game = finalPhaseGame();
    $company = $game->company;
    $balance = $company->inventoryBalances()->firstOrFail();
    $balance->update(['quantity' => 10, 'average_cost_cents' => 500]);
    $company->financialEntries()->create([
        'type' => 'inflow', 'category' => 'receivable', 'description' => 'Receber',
        'amount_cents' => 3_000, 'game_date' => $game->current_date, 'due_date' => $game->current_date->addDay(),
    ]);
    $company->financialEntries()->create([
        'type' => 'outflow', 'category' => 'operating_expense', 'description' => 'Pagar',
        'amount_cents' => 2_000, 'game_date' => $game->current_date, 'due_date' => $game->current_date->addDay(),
    ]);

    expect(app(VictoryCondition::class)->equityCents($game))->toBe(7_756_000);
});
