<?php

namespace App\Domain\Hr\Actions;

use App\Domain\Hr\Services\SalaryMatrixService;
use App\Models\Employee;
use App\Models\Game;
use App\Models\PopulationNpc;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HireEmployee
{
    public function __construct(private readonly SalaryMatrixService $salaryMatrix) {}

    /** @param array{population_npc_id: int, department: string, role: string} $data */
    public function execute(Game $game, array $data): Employee
    {
        return DB::transaction(function () use ($game, $data) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            if ($lockedGame->status !== 'active') {
                throw ValidationException::withMessages(['game' => 'A partida não está ativa.']);
            }

            $company = $lockedGame->company()->lockForUpdate()->firstOrFail();
            $npc = PopulationNpc::query()->lockForUpdate()->findOrFail($data['population_npc_id']);
            if ($npc->game_id !== $lockedGame->id) {
                throw ValidationException::withMessages(['population_npc_id' => 'Candidato inválido para esta partida.']);
            }

            if ($company->employees()->where('population_npc_id', $npc->id)->exists()) {
                throw ValidationException::withMessages(['population_npc_id' => 'Este candidato já foi contratado.']);
            }

            $employee = $company->employees()->create([
                'population_npc_id' => $npc->id,
                'name' => $npc->name,
                'department' => $data['department'],
                'role' => $data['role'],
                'monthly_salary_cents' => $this->salaryMatrix->salaryCents($data['department'], $data['role']),
                'hired_on' => $lockedGame->current_date,
                'status' => 'active',
            ]);
            $payrollDay = config('game.hr.payroll_day');
            $dueDate = $lockedGame->current_date->copy()->setDay($payrollDay);
            if ($dueDate->isBefore($lockedGame->current_date)) {
                $dueDate = $dueDate->addMonthNoOverflow();
            }

            $employee->salaryEntries()->create([
                'company_id' => $company->id,
                'type' => 'outflow',
                'category' => 'payroll',
                'description' => "Salário - {$employee->name}",
                'amount_cents' => $employee->monthly_salary_cents,
                'game_date' => $dueDate,
                'due_date' => $dueDate,
                'recurring' => true,
                'metadata' => ['employee_id' => $employee->id],
            ]);

            return $employee->load('salaryEntries');
        });
    }
}
