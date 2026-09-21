<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Purchasing\Services\AutomaticPurchasingService;
use App\Models\Employee;
use App\Models\Game;
use App\Models\InboxMessage;
use App\Models\JobRole;
use App\Models\PopulationNpc;
use App\Models\PurchaseOrder;
use App\Models\User;

function purchasingGame(int $seed = 7100): Game
{
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Compras automáticas', 'Mercado Aurora', $seed);
    $company = $game->company;

    foreach ($company->inventoryBalances()->with('product')->get() as $balance) {
        $balance->update(['quantity' => ($balance->product->base_daily_demand * 2) + 1, 'average_cost_cents' => 1_000]);
    }
    $company->inventoryBalances()->orderBy('id')->firstOrFail()->update(['quantity' => 0]);
    createOperationalEmployee($game, 'Logística', 'Diretor', 'Responsável Logístico', 0);

    return $game->fresh('company');
}

function createOperationalEmployee(Game $game, string $department, string $role, string $name, int $offset): Employee
{
    return $game->company->employees()->create([
        'population_npc_id' => PopulationNpc::query()->orderBy('id')->skip($offset)->value('id'),
        'name' => $name,
        'department' => $department,
        'role' => $role,
        'monthly_salary_cents' => JobRole::where('department', $department)->where('name', $role)->value('salary_cents'),
        'hired_on' => $game->current_date,
        'status' => 'active',
    ]);
}

test('automatic purchasing does nothing without an active purchasing employee', function () {
    $game = purchasingGame();

    $result = app(AutomaticPurchasingService::class)->process($game);

    expect($result['order_ids'])->toBeEmpty()
        ->and(PurchaseOrder::count())->toBe(0);
});

test('automatic purchasing respects the configured role capacity and records the buyer', function () {
    $game = purchasingGame(7101);
    JobRole::where('department', 'Compras')->where('name', 'Assistente')->update(['purchasing_capacity_units' => 3]);
    $buyer = createOperationalEmployee($game, 'Compras', 'Assistente', 'Compradora Assistente', 1);

    $result = app(AutomaticPurchasingService::class)->process($game);
    $order = PurchaseOrder::firstOrFail();

    expect($result['units'])->toBe(3)
        ->and($order->automatic)->toBeTrue()
        ->and($order->employee_id)->toBe($buyer->id)
        ->and($order->items->sum('quantity'))->toBe(3);
});

test('the most senior buyer acts first and incoming stock prevents duplicate replenishment', function () {
    $game = purchasingGame(7102);
    $assistant = createOperationalEmployee($game, 'Compras', 'Assistente', 'Comprador Assistente', 1);
    $director = createOperationalEmployee($game, 'Compras', 'Diretor', 'Compradora Diretora', 2);

    $first = app(AutomaticPurchasingService::class)->process($game);
    $second = app(AutomaticPurchasingService::class)->process($game);

    expect($first['order_ids'])->toHaveCount(1)
        ->and(PurchaseOrder::firstOrFail()->employee_id)->toBe($director->id)
        ->and(PurchaseOrder::firstOrFail()->employee_id)->not->toBe($assistant->id)
        ->and($second['order_ids'])->toBeEmpty()
        ->and(PurchaseOrder::count())->toBe(1);
});

test('daily processing creates automatic orders and notifies the owner', function () {
    $game = purchasingGame(7103);
    createOperationalEmployee($game, 'Compras', 'Diretor', 'Diretora de Compras', 1);

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['automatic_purchase_order_ids'])->not->toBeEmpty()
        ->and($summary['automatic_purchase_units'])->toBeGreaterThan(0)
        ->and(PurchaseOrder::where('automatic', true)->exists())->toBeTrue()
        ->and(InboxMessage::where('recipient_user_id', $game->user_id)
            ->where('category', 'purchase')
            ->where('subject', 'like', 'Pedido automático #%')->exists())->toBeTrue();
});
