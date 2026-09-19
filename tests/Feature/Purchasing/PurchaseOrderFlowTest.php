<?php

use App\Domain\Game\Actions\CreateGame;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Models\FinancialEntry;
use App\Models\Game;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function phaseTwoGame(): Game
{
    return app(CreateGame::class)->execute(User::factory()->create(), 'Compras', 'Mercado Aurora', 200);
}

test('a term purchase uses server prices and creates an account payable', function () {
    $game = phaseTwoGame();
    $supplier = $game->company->suppliers()->where('name', 'Distribuidora Alfa')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();

    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 10, 'unit_cost_cents' => 1],
    ]);

    expect($order->total_cents)->toBe($offer->cost_cents * 10)
        ->and($order->financialEntry->paid_at)->toBeNull()
        ->and($order->financialEntry->due_date->toDateString())->toBe('2026-01-08')
        ->and($game->company->fresh()->cash_balance_cents)->toBe(10_000_000);
});

test('a cash purchase debits cash and records a paid financial entry', function () {
    $game = phaseTwoGame();
    $supplier = $game->company->suppliers()->where('name', 'Atacado Econômico')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();

    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 5],
    ]);

    expect($game->company->fresh()->cash_balance_cents)->toBe(10_000_000 - ($offer->cost_cents * 5))
        ->and($order->financialEntry->paid_at)->not->toBeNull();
});

test('insufficient cash rolls back a cash purchase', function () {
    $game = phaseTwoGame();
    $game->company->update(['cash_balance_cents' => 1]);
    $supplier = $game->company->suppliers()->where('name', 'Atacado Econômico')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();

    expect(fn () => app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 1],
    ]))->toThrow(ValidationException::class);

    expect(PurchaseOrder::count())->toBe(0)
        ->and(FinancialEntry::where('category', 'purchase')->count())->toBe(0);
});

test('receiving a purchase updates weighted average and is idempotent', function () {
    $game = phaseTwoGame();
    $company = $game->company;
    $product = $company->products()->firstOrFail();
    $firstSupplier = $company->suppliers()->where('name', 'Distribuidora Alfa')->firstOrFail();
    $secondSupplier = $company->suppliers()->where('name', 'Entrega Expressa')->firstOrFail();

    $firstOrder = app(CreatePurchaseOrder::class)->execute($game, $firstSupplier, [
        ['product_id' => $product->id, 'quantity' => 10],
    ]);
    $game->update(['current_date' => $firstOrder->expected_delivery_date]);
    app(ReceivePurchaseOrder::class)->execute($game->fresh(), $firstOrder);

    $secondOrder = app(CreatePurchaseOrder::class)->execute($game->fresh(), $secondSupplier, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]);
    $game->update(['current_date' => $secondOrder->expected_delivery_date]);
    app(ReceivePurchaseOrder::class)->execute($game->fresh(), $secondOrder);
    app(ReceivePurchaseOrder::class)->execute($game->fresh(), $secondOrder);

    $balance = $company->inventoryBalances()->where('product_id', $product->id)->firstOrFail();
    $firstUnitCost = $firstOrder->items->first()->unit_cost_cents;
    $secondUnitCost = $secondOrder->items->first()->unit_cost_cents;
    $expectedAverage = intdiv((10 * $firstUnitCost) + (5 * $secondUnitCost) + 7, 15);

    expect($balance->quantity)->toBe(15)
        ->and($balance->average_cost_cents)->toBe($expectedAverage)
        ->and(InventoryMovement::count())->toBe(2);
});

test('an order cannot be received before its expected date', function () {
    $game = phaseTwoGame();
    $supplier = $game->company->suppliers()->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 1],
    ]);

    expect(fn () => app(ReceivePurchaseOrder::class)->execute($game, $order))
        ->toThrow(ValidationException::class);

    expect(InventoryMovement::count())->toBe(0);
});

test('completed inventory movements cannot be changed or deleted', function () {
    $game = phaseTwoGame();
    $supplier = $game->company->suppliers()->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 2],
    ]);
    $game->update(['current_date' => $order->expected_delivery_date]);
    app(ReceivePurchaseOrder::class)->execute($game->fresh(), $order);
    $movement = InventoryMovement::firstOrFail();

    expect(fn () => $movement->update(['quantity' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});
