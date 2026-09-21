<?php

use App\Domain\Finance\Services\IncomeStatementService;
use App\Domain\Finance\Services\SettleDuePayables;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Hr\Actions\HireEmployee;
use App\Domain\Hr\Actions\TerminateEmployee;
use App\Models\Employee;
use App\Models\Game;
use App\Models\PopulationNpc;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function hrGame(): Game
{
    return app(CreateGame::class)->execute(User::factory()->create(), 'Equipe', 'Mercado Aurora', 950);
}

function hrHireData(Game $game, string $department, string $role, int $offset = 0): array
{
    return [
        'population_npc_id' => PopulationNpc::query()->orderBy('id')->skip($offset)->value('id'),
        'department' => $department,
        'role' => $role,
    ];
}

test('hiring creates an active employee and a recurring salary payable', function () {
    $game = hrGame();

    $employee = app(HireEmployee::class)->execute($game, hrHireData($game, 'Comercial', 'Analista'));

    $salary = $employee->salaryEntries->first();
    expect($employee->status)->toBe('active')
        ->and($employee->hired_on->toDateString())->toBe('2026-01-01')
        ->and($employee->monthly_salary_cents)->toBe(420_000)
        ->and($salary->amount_cents)->toBe(420_000)
        ->and($salary->due_date->toDateString())->toBe('2026-01-10')
        ->and($salary->reference_id)->toBe($employee->id);
});

test('an active employee salary schedules the following month', function () {
    $game = hrGame();
    $employee = app(HireEmployee::class)->execute($game, hrHireData($game, 'Operações', 'Assistente'));

    app(SettleDuePayables::class)->settle($game->company, CarbonImmutable::parse('2026-01-10'));

    expect($employee->salaryEntries()->count())->toBe(2)
        ->and($employee->salaryEntries()->whereDate('due_date', '2026-02-10')->count())->toBe(1);
});

test('termination is idempotent and prevents another salary recurrence', function () {
    $game = hrGame();
    $employee = app(HireEmployee::class)->execute($game, hrHireData($game, 'Logística', 'Coordenador'));
    app(TerminateEmployee::class)->execute($game, $employee);
    app(TerminateEmployee::class)->execute($game, $employee);

    app(SettleDuePayables::class)->settle($game->company, CarbonImmutable::parse('2026-01-10'));

    expect($employee->fresh()->status)->toBe('terminated')
        ->and($employee->fresh()->terminated_on->toDateString())->toBe('2026-01-01')
        ->and($employee->salaryEntries()->count())->toBe(1);
});

test('an employee from another company cannot be terminated', function () {
    $game = hrGame();
    $otherGame = hrGame();
    $foreignEmployee = Employee::factory()->create(['company_id' => $otherGame->company->id]);

    expect(fn () => app(TerminateEmployee::class)->execute($game, $foreignEmployee))
        ->toThrow(ValidationException::class);
});

test('salary is recognized by the income statement on its competence date', function () {
    $game = hrGame();
    app(HireEmployee::class)->execute($game, hrHireData($game, 'Administração', 'Gerente'));

    $statement = app(IncomeStatementService::class)->calculate(
        $game->company,
        CarbonImmutable::parse('2026-01-10'),
    );

    expect($statement['operatingExpensesCents'])->toBe(2_800_000);
});
