<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\Employee;
use App\Models\PopulationNpc;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner sees team and payroll summary', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Equipe', 'Mercado Aurora', 960);

    $this->actingAs($user)
        ->get(route('games.team.index', $game))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Team/Index')
            ->where('summary.activeEmployees', 0)
            ->has('options.departments', 4)
            ->has('options.roles', 5)
            ->has('options.salaryMatrix.Administração', 5));
});

test('the owner hires and terminates through validated endpoints', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Equipe', 'Mercado Aurora', 961);

    $npc = PopulationNpc::query()->firstOrFail();
    $this->actingAs($user)->post(route('games.employees.store', $game), [
        'population_npc_id' => $npc->id,
        'department' => 'Administração',
        'role' => 'Gerente',
        'name' => 'Nome manipulado',
        'monthly_salary_cents' => 1,
        'company_id' => 999,
    ])->assertRedirect(route('games.team.index', $game));
    $employee = Employee::firstOrFail();
    expect($employee->company_id)->toBe($game->company->id)
        ->and($employee->name)->toBe($npc->name)
        ->and($employee->monthly_salary_cents)->toBe(800_000);

    $this->actingAs($user)
        ->get(route('games.team.index', $game))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.activeEmployees', 1)
            ->where('summary.monthlyPayrollCents', 800_000)
            ->where('summary.nextPayrollCents', 800_000));

    $this->actingAs($user)
        ->delete(route('games.employees.terminate', [$game, $employee]))
        ->assertRedirect(route('games.team.index', $game));
    expect($employee->fresh()->status)->toBe('terminated');
});

test('the wizard returns three candidates with salary from the matrix', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Wizard', 'Mercado Aurora', 963);

    $this->actingAs($user)
        ->get(route('games.team.index', [
            'game' => $game,
            'department' => 'Comercial',
            'role' => 'Diretor',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('candidateSuggestions', 3)
            ->where('candidateSuggestions.0.department', 'Comercial')
            ->where('candidateSuggestions.0.role', 'Diretor')
            ->where('candidateSuggestions.0.salaryCents', 1_350_000));
});

test('another user cannot access or mutate the team', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Privada', 962);

    $this->actingAs($intruder)->get(route('games.team.index', $game))->assertForbidden();
    $this->actingAs($intruder)->post(route('games.employees.store', $game), [
        'population_npc_id' => PopulationNpc::query()->value('id'), 'department' => 'Comercial', 'role' => 'Analista',
    ])->assertForbidden();
});
