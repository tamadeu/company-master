<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\Employee;
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
            ->has('options.roles', 4));
});

test('the owner hires and terminates through validated endpoints', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Equipe', 'Mercado Aurora', 961);

    $this->actingAs($user)->post(route('games.employees.store', $game), [
        'name' => 'Daniel Costa',
        'department' => 'Administração',
        'role' => 'Gerente',
        'monthly_salary_cents' => 600_000,
        'company_id' => 999,
    ])->assertRedirect(route('games.team.index', $game));
    $employee = Employee::firstOrFail();
    expect($employee->company_id)->toBe($game->company->id);

    $this->actingAs($user)
        ->get(route('games.team.index', $game))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.activeEmployees', 1)
            ->where('summary.monthlyPayrollCents', 600_000)
            ->where('summary.nextPayrollCents', 600_000));

    $this->actingAs($user)
        ->delete(route('games.employees.terminate', [$game, $employee]))
        ->assertRedirect(route('games.team.index', $game));
    expect($employee->fresh()->status)->toBe('terminated');
});

test('another user cannot access or mutate the team', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Privada', 962);

    $this->actingAs($intruder)->get(route('games.team.index', $game))->assertForbidden();
    $this->actingAs($intruder)->post(route('games.employees.store', $game), [
        'name' => 'Invasor', 'department' => 'Comercial', 'role' => 'Analista', 'monthly_salary_cents' => 1,
    ])->assertForbidden();
});
