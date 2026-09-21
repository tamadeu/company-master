<?php

use App\Domain\Game\Actions\CreateGame;
use App\Domain\Hr\Services\SalaryMatrixService;
use App\Models\JobRole;
use App\Models\PopulationNpc;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('only administrators can access the admin area', function () {
    $player = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($player)->get(route('admin.overview'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Index')
            ->where('section', 'overview')
            ->where('stats.users', 2));
});

test('an administrator can open every management module', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    foreach ([
        'admin.users.index' => 'users',
        'admin.games.index' => 'games',
        'admin.population.index' => 'population',
        'admin.products.index' => 'products',
        'admin.job-roles.index' => 'job-roles',
    ] as $routeName => $section) {
        $this->actingAs($admin)->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Index')
                ->where('section', $section)
                ->has('records.data'));
    }
});

test('an administrator can create master data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $game = app(CreateGame::class)->execute($admin, 'Administração', 'Empresa Admin', 3001);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Novo Jogador',
        'email' => 'jogador@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_admin' => false,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.population.store'), [
        'code' => 'NPC-ADMIN',
        'name' => 'Pessoa Administrada',
        'birth_date' => '1990-05-10',
        'gender' => 'Feminino',
        'city' => 'Curitiba',
        'state' => 'PR',
        'email' => 'pessoa@example.com',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.products.store'), [
        'sku' => 'NOV-001',
        'name' => 'Produto Novo',
        'reference_price_cents' => 2500,
        'base_daily_demand' => 9,
        'base_cost_cents' => 1200,
        'active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.job-roles.store'), [
        'department' => 'Tecnologia',
        'name' => 'Especialista',
        'salary_cents' => 900000,
        'sales_capacity_units' => 0,
        'inventory_capacity_units' => 0,
        'purchasing_capacity_units' => 12,
        'active' => true,
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'jogador@example.com')->exists())->toBeTrue()
        ->and(PopulationNpc::where('code', 'NPC-ADMIN')->exists())->toBeTrue()
        ->and(ProductTemplate::where('sku', 'NOV-001')->exists())->toBeTrue()
        ->and(JobRole::where('department', 'Tecnologia')->where('name', 'Especialista')->value('purchasing_capacity_units'))->toBe(12);
});

test('an administrator cannot remove their own access or delete linked population', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $game = app(CreateGame::class)->execute($admin, 'Proteções', 'Empresa Protegida', 3002);
    $person = PopulationNpc::query()->firstOrFail();
    $game->company->employees()->create([
        'population_npc_id' => $person->id,
        'name' => $person->name,
        'department' => 'Administração',
        'role' => 'Assistente',
        'monthly_salary_cents' => 250000,
        'hired_on' => $game->current_date,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => false,
    ])->assertSessionHasErrors('is_admin');

    $this->actingAs($admin)->delete(route('admin.population.destroy', $person))
        ->assertSessionHasErrors('population');

    expect($admin->fresh()->is_admin)->toBeTrue()
        ->and($person->fresh())->not->toBeNull();
});

test('managed products and salaries affect future game rules', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    ProductTemplate::where('sku', 'CAF-500')->update(['active' => false]);
    JobRole::where('department', 'Comercial')->where('name', 'Analista')->update(['salary_cents' => 777000]);

    $game = app(CreateGame::class)->execute($admin, 'Catálogo', 'Empresa Catálogo', 3003);

    expect(Product::where('company_id', $game->company->id)->count())->toBe(4)
        ->and(Product::where('company_id', $game->company->id)->where('sku', 'CAF-500')->exists())->toBeFalse()
        ->and(app(SalaryMatrixService::class)->salaryCents('Comercial', 'Analista'))->toBe(777000);
});

test('a super administrator can manage games from every player', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $firstPlayer = User::factory()->create();
    $secondPlayer = User::factory()->create();
    $game = app(CreateGame::class)->execute($firstPlayer, 'Original', 'Empresa Original', 3004);

    $this->actingAs($admin)->patch(route('admin.games.update', $game), [
        'user_id' => $secondPlayer->id,
        'name' => 'Transferida',
        'company_name' => 'Empresa Transferida',
        'status' => 'won',
        'current_date' => '2026-02-15',
    ])->assertSessionHasNoErrors();

    expect($game->fresh()->user_id)->toBe($secondPlayer->id)
        ->and($game->fresh()->name)->toBe('Transferida')
        ->and($game->fresh()->status)->toBe('won')
        ->and($game->fresh()->company->name)->toBe('Empresa Transferida');

    $this->actingAs($admin)->delete(route('admin.games.destroy', $game))->assertSessionHasNoErrors();
    expect($game->fresh())->toBeNull();
});
