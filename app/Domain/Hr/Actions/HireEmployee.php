<?php

namespace App\Domain\Hr\Actions;

use App\Models\Employee;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HireEmployee
{
    /** @param array{name: string, department: string, role: string, monthly_salary_cents: int} $data */
    public function execute(Game $game, array $data): Employee
    {
        return DB::transaction(function () use ($game, $data) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            if ($lockedGame->status !== 'active') {
                throw ValidationException::withMessages(['game' => 'A partida não está ativa.']);
            }

            $company = $lockedGame->company()->lockForUpdate()->firstOrFail();
            $employee = $company->employees()->create([
                ...$data,
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
