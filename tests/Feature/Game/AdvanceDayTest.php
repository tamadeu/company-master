<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Sales\Services\SalesSimulator;
use App\Models\DailySnapshot;
use App\Models\DayProcess;
use App\Models\Game;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function phaseThreeGame(int $seed = 900): Game
{
    return app(CreateGame::class)->execute(
        User::factory()->create(),
        'Motor diário',
        'Mercado Aurora',
        $seed,
    );
}

function stockAllProducts(Game $game, int $quantity = 100): void
{
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update([
            'quantity' => $quantity,
            'average_cost_cents' => 1_000,
        ]);
    }
}

test('equivalent games with the same seed produce the same sales result', function () {
    $firstGame = phaseThreeGame(777);
    $secondGame = phaseThreeGame(777);
    stockAllProducts($firstGame);
    stockAllProducts($secondGame);

    $firstSummary = app(AdvanceDay::class)->execute($firstGame, '2026-01-01');
    $secondSummary = app(AdvanceDay::class)->execute($secondGame, '2026-01-01');

    expect($firstSummary['sales_revenue_cents'])->toBe($secondSummary['sales_revenue_cents'])
        ->and($firstSummary['units_sold'])->toBe($secondSummary['units_sold'])
        ->and($firstSummary['cogs_cents'])->toBe($secondSummary['cogs_cents']);
});

test('sales never exceed available stock', function () {
    $game = phaseThreeGame();
    $balance = $game->company->inventoryBalances()->firstOrFail();
    $balance->update(['quantity' => 2, 'average_cost_cents' => 900]);

    app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect(SaleItem::sum('quantity'))->toBeLessThanOrEqual(2)
        ->and($balance->fresh()->quantity)->toBeGreaterThanOrEqual(0);
});

test('the same expected date is processed only once', function () {
    $game = phaseThreeGame();
    stockAllProducts($game, 20);

    $firstSummary = app(AdvanceDay::class)->execute($game, '2026-01-01');
    $secondSummary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($secondSummary)->toBe($firstSummary)
        ->and(DayProcess::where('status', 'completed')->count())->toBe(1)
        ->and(DailySnapshot::count())->toBe(1)
        ->and(Sale::count())->toBe(1)
        ->and($game->fresh()->current_date->toDateString())->toBe('2026-01-02');
});

test('due purchases are received before sales are simulated', function () {
    $game = phaseThreeGame();
    $supplier = $game->company->suppliers()->where('name', 'Entrega Expressa')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 100],
    ]);

    app(AdvanceDay::class)->execute($game, '2026-01-01');
    $summary = app(AdvanceDay::class)->execute($game->fresh(), '2026-01-02');

    expect($summary['received_purchase_order_ids'])->toContain($order->id)
        ->and($summary['units_sold'])->toBeGreaterThan(0)
        ->and(InventoryMovement::where('type', 'purchase_receipt')->count())->toBe(1)
        ->and(InventoryMovement::where('type', 'sale')->count())->toBeGreaterThan(0);
});

test('an overdue obligation without cash ends the game in bankruptcy', function () {
    $game = phaseThreeGame();
    stockAllProducts($game, 20);
    $game->company->financialEntries()->create([
        'type' => 'outflow',
        'category' => 'test_obligation',
        'description' => 'Obrigação sem cobertura',
        'amount_cents' => 20_000_000,
        'game_date' => $game->current_date,
        'due_date' => $game->current_date,
    ]);

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['game_status'])->toBe('bankrupt')
        ->and($game->fresh()->status)->toBe('bankrupt')
        ->and(Sale::count())->toBe(0)
        ->and(DailySnapshot::count())->toBe(1)
        ->and(InventoryMovement::count())->toBe(0)
        ->and(DayProcess::where('status', 'completed')->count())->toBe(1);
});

test('a technical processing failure rolls back the complete day', function () {
    $game = phaseThreeGame();
    stockAllProducts($game, 20);
    $simulator = Mockery::mock(SalesSimulator::class);
    $simulator->shouldReceive('simulate')->once()->andThrow(new RuntimeException('Falha simulada'));
    $this->app->instance(SalesSimulator::class, $simulator);

    expect(fn () => app(AdvanceDay::class)->execute($game, '2026-01-01'))
        ->toThrow(RuntimeException::class);

    expect($game->fresh()->current_date->toDateString())->toBe('2026-01-01')
        ->and(Sale::count())->toBe(0)
        ->and(DailySnapshot::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0)
        ->and(DayProcess::where('status', 'failed')->count())->toBe(1);
});

test('an inactive game cannot advance', function () {
    $game = phaseThreeGame();
    $game->update(['status' => 'finished']);

    expect(fn () => app(AdvanceDay::class)->execute($game, '2026-01-01'))
        ->toThrow(ValidationException::class);

    expect($game->fresh()->current_date->toDateString())->toBe('2026-01-01');
});

test('due obligations are paid before sales', function () {
    $game = phaseThreeGame();
    $entry = $game->company->financialEntries()->create([
        'type' => 'outflow',
        'category' => 'test_obligation',
        'description' => 'Obrigação do dia',
        'amount_cents' => 1_000,
        'game_date' => $game->current_date,
        'due_date' => $game->current_date,
    ]);

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['expenses_cents'])->toBe(1_000)
        ->and($summary['cash_change_cents'])->toBe(-1_000)
        ->and($entry->fresh()->paid_at)->not->toBeNull()
        ->and($game->company->fresh()->cash_balance_cents)->toBe(9_999_000);
});

test('the daily snapshot matches generated sales cash and inventory', function () {
    $game = phaseThreeGame();
    stockAllProducts($game, 30);
    $cashBefore = $game->company->cash_balance_cents;

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');
    $snapshot = DailySnapshot::firstOrFail();
    $sale = Sale::firstOrFail();
    $inventoryValueCents = (int) $game->company->inventoryBalances()->get()->sum(
        fn ($balance) => $balance->quantity * $balance->average_cost_cents,
    );

    expect($snapshot->sales_revenue_cents)->toBe($sale->revenue_cents)
        ->and($snapshot->cogs_cents)->toBe($sale->cogs_cents)
        ->and($snapshot->units_sold)->toBe(SaleItem::sum('quantity'))
        ->and($snapshot->cash_change_cents)->toBe($game->company->fresh()->cash_balance_cents - $cashBefore)
        ->and($snapshot->inventory_value_cents)->toBe($inventoryValueCents)
        ->and($snapshot->summary)->toBe($summary);
});

test('completed daily snapshots cannot be changed or deleted', function () {
    $game = phaseThreeGame();
    app(AdvanceDay::class)->execute($game, '2026-01-01');
    $snapshot = DailySnapshot::firstOrFail();

    expect(fn () => $snapshot->update(['units_sold' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $snapshot->delete())->toThrow(LogicException::class);
});
