<?php

use App\Domain\Game\Actions\CreateGame;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Domain\Purchasing\Actions\CancelPurchaseOrder;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Models\FinancialEntry;
use App\Models\Game;
use App\Models\InboxMessage;
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
        ->and($game->company->fresh()->cash_balance_cents)->toBe(10_000_000)
        ->and(InboxMessage::where('recipient_user_id', $game->user_id)
            ->where('category', 'purchase')
            ->where('subject', "Pedido de compra #{$order->id} criado")
            ->where('metadata->order_id', $order->id)
            ->exists())->toBeTrue();
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

test('a zero-day supplier delivers into inventory immediately', function () {
    $game = phaseTwoGame();
    $company = $game->company;
    $supplier = $company->suppliers()->where('name', 'Pronta Entrega')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $standardOffer = $company->suppliers()
        ->where('name', 'Distribuidora Alfa')
        ->firstOrFail()
        ->products()
        ->where('product_id', $offer->product_id)
        ->firstOrFail();

    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 4],
    ]);

    $balance = $company->inventoryBalances()->where('product_id', $offer->product_id)->firstOrFail();
    expect($offer->cost_cents)->toBeGreaterThan($standardOffer->cost_cents)
        ->and($order->status)->toBe('received')
        ->and($order->received_at_game_date->toDateString())->toBe($game->current_date->toDateString())
        ->and($balance->quantity)->toBe(4)
        ->and(InventoryMovement::count())->toBe(1);
});

test('cancelling an unpaid order charges ten percent and releases inventory capacity', function () {
    $game = phaseTwoGame();
    $company = $game->company;
    $supplier = $company->suppliers()->where('name', 'Distribuidora Alfa')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 10],
    ]);
    $cashBefore = $company->fresh()->cash_balance_cents;

    $cancelled = app(CancelPurchaseOrder::class)->execute($game, $order);

    $feeCents = intdiv($order->total_cents + 5, 10);
    $capacity = app(InventoryCapacityService::class)->calculate($company);
    expect($cancelled->status)->toBe('cancelled')
        ->and($cancelled->financialEntry)->toBeNull()
        ->and($company->fresh()->cash_balance_cents)->toBe($cashBefore - $feeCents)
        ->and($capacity['incoming_units'])->toBe(0)
        ->and($capacity['available_units'])->toBe($capacity['capacity_units']);
    $this->assertDatabaseHas('financial_entries', [
        'company_id' => $company->id,
        'category' => 'purchase_cancellation_fee',
        'amount_cents' => $feeCents,
    ]);
});

test('cancelling a paid order refunds its value before charging the fee', function () {
    $game = phaseTwoGame();
    $company = $game->company;
    $initialCash = $company->cash_balance_cents;
    $supplier = $company->suppliers()->where('name', 'Atacado Econômico')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 5],
    ]);

    app(CancelPurchaseOrder::class)->execute($game, $order);

    $feeCents = intdiv($order->total_cents + 5, 10);
    expect($company->fresh()->cash_balance_cents)->toBe($initialCash - $feeCents)
        ->and($order->fresh()->status)->toBe('cancelled');
    $this->assertDatabaseHas('financial_entries', [
        'company_id' => $company->id,
        'category' => 'purchase_refund',
        'amount_cents' => $order->total_cents,
    ]);
});

test('a received order cannot be cancelled', function () {
    $game = phaseTwoGame();
    $supplier = $game->company->suppliers()->where('name', 'Pronta Entrega')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 1],
    ]);

    expect(fn () => app(CancelPurchaseOrder::class)->execute($game, $order))
        ->toThrow(ValidationException::class)
        ->and($order->fresh()->status)->toBe('received');
});

test('insufficient cash keeps an unpaid order and its reserved capacity', function () {
    $game = phaseTwoGame();
    $company = $game->company;
    $supplier = $company->suppliers()->where('name', 'Distribuidora Alfa')->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 10],
    ]);
    $company->update(['cash_balance_cents' => 0]);

    expect(fn () => app(CancelPurchaseOrder::class)->execute($game, $order))
        ->toThrow(ValidationException::class)
        ->and($order->fresh()->status)->toBe('ordered')
        ->and(app(InventoryCapacityService::class)->calculate($company)['incoming_units'])->toBe(10);
    $this->assertDatabaseMissing('financial_entries', [
        'company_id' => $company->id,
        'category' => 'purchase_cancellation_fee',
    ]);
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
