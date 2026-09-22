<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\FinancialEntry;
use App\Models\Game;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

test('a game is created transactionally with its initial company data', function () {
    $user = User::factory()->create();

    $game = app(CreateGame::class)->execute($user, 'Minha primeira partida', 'Mercado Aurora', 12345);

    expect($game->user_id)->toBe($user->id)
        ->and($game->seed)->toBe(12345)
        ->and($game->current_date->toDateString())->toBe('2026-01-01')
        ->and($game->company->name)->toBe('Mercado Aurora')
        ->and($game->company->cash_balance_cents)->toBe(10_000_000)
        ->and(Product::count())->toBe(5)
        ->and(Supplier::count())->toBe(4)
        ->and(SupplierProduct::count())->toBe(20)
        ->and(InventoryBalance::count())->toBe(5)
        ->and(InventoryBalance::sum('quantity'))->toBe(0)
        ->and(FinancialEntry::count())->toBe(4)
        ->and(FinancialEntry::where('category', 'initial_capital')->value('amount_cents'))->toBe(10_000_000);
});

test('a failure rolls back the complete game creation', function () {
    $user = User::factory()->create();
    Config::set('game.suppliers.0.cost_percent', 0);

    expect(fn () => app(CreateGame::class)->execute($user, 'Partida inválida', 'Empresa inválida', 12345))
        ->toThrow(QueryException::class);

    expect(Game::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(FinancialEntry::count())->toBe(0);
});

test('the dashboard exposes manual advancement only in local environment', function () {
    $this->app->instance('env', 'local');
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Local', 'Empresa local', 12346);

    $this->actingAs($user)
        ->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('manualAdvanceEnabled', true));
});

test('production games are scheduled for midnight and hide manual advancement', function () {
    CarbonImmutable::setTestNow('2026-09-21 18:30:00');
    $this->app->instance('env', 'production');
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Produção', 'Empresa automática', 12347);

    expect($game->automation_enabled)->toBeTrue()
        ->and($game->next_processing_at?->toDateTimeString())->toBe('2026-09-22 00:00:00');

    $this->actingAs($user)
        ->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('manualAdvanceEnabled', false));
});

test('the new game flow opens onboarding while preserving existing games', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Existente', 'Empresa existente', 12348);

    $this->actingAs($user)
        ->get(route('dashboard', ['new' => 1]))
        ->assertRedirect(route('games.show', ['game' => $game, 'new' => 1]));

    $this->actingAs($user)
        ->get(route('games.show', ['game' => $game, 'new' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('startNewGame', true)
            ->has('games', 1));
});
