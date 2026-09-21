<?php

use App\Domain\Game\Actions\CreateGame;
use App\Domain\Hr\Actions\HireEmployee;
use App\Domain\Hr\Actions\TerminateEmployee;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Models\Employee;
use App\Models\Game;
use App\Models\PopulationNpc;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function capacityGame(int $seed = 1200): Game
{
    return app(CreateGame::class)->execute(User::factory()->create(), 'Capacidade', 'Mercado Aurora', $seed);
}

function hireLogistics(Game $game, string $role, int $offset = 0): Employee
{
    return app(HireEmployee::class)->execute($game, [
        'population_npc_id' => PopulationNpc::query()->orderBy('id')->skip($offset)->value('id'),
        'department' => 'Logística',
        'role' => $role,
    ]);
}

test('base inventory capacity is fifty units without logistics employees', function () {
    $capacity = app(InventoryCapacityService::class)->calculate(capacityGame()->company);

    expect($capacity['capacity_units'])->toBe(50)
        ->and($capacity['used_units'])->toBe(0)
        ->and($capacity['available_units'])->toBe(50)
        ->and($capacity['logistics_employees'])->toBe(0);
});

test('each logistics role adds its configured capacity', function () {
    $game = capacityGame();
    $expectedCapacity = 50;
    $candidateOffset = 0;

    foreach (config('game.inventory.capacity_by_logistics_role') as $role => $addition) {
        hireLogistics($game, $role, $candidateOffset);
        $candidateOffset++;
        $expectedCapacity += $addition;

        expect(app(InventoryCapacityService::class)->calculate($game->company)['capacity_units'])
            ->toBe($expectedCapacity);
    }
});

test('ordered units reserve capacity and excessive orders are rejected', function () {
    $game = capacityGame();
    $supplier = $game->company->suppliers()->firstOrFail();
    $offers = $supplier->products()->take(2)->get();

    app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offers[0]->product_id, 'quantity' => 40],
    ]);
    $capacity = app(InventoryCapacityService::class)->calculate($game->company);

    expect($capacity['stock_units'])->toBe(0)
        ->and($capacity['incoming_units'])->toBe(40)
        ->and($capacity['available_units'])->toBe(10);

    expect(fn () => app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offers[1]->product_id, 'quantity' => 11],
    ]))->toThrow(ValidationException::class);
});

test('receiving moves reserved units into stock without changing used capacity', function () {
    $game = capacityGame();
    $supplier = $game->company->suppliers()->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    $order = app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 30],
    ]);
    $game->update(['current_date' => $order->expected_delivery_date]);

    app(ReceivePurchaseOrder::class)->execute($game->fresh(), $order);
    $capacity = app(InventoryCapacityService::class)->calculate($game->company);

    expect($capacity['stock_units'])->toBe(30)
        ->and($capacity['incoming_units'])->toBe(0)
        ->and($capacity['used_units'])->toBe(30);
});

test('a logistics employee cannot be terminated when capacity would be exceeded', function () {
    $game = capacityGame();
    $employee = hireLogistics($game, 'Assistente');
    $supplier = $game->company->suppliers()->firstOrFail();
    $offer = $supplier->products()->firstOrFail();
    app(CreatePurchaseOrder::class)->execute($game, $supplier, [
        ['product_id' => $offer->product_id, 'quantity' => 80],
    ]);

    expect(fn () => app(TerminateEmployee::class)->execute($game, $employee))
        ->toThrow(ValidationException::class);
    expect($employee->fresh()->status)->toBe('active');
});
