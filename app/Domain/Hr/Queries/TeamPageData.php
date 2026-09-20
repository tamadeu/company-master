<?php

namespace App\Domain\Hr\Queries;

use App\Domain\Hr\Services\CandidateRecommendationService;
use App\Domain\Hr\Services\SalaryMatrixService;
use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Domain\Sales\Services\SalesCapacityService;
use App\Models\Game;

class TeamPageData
{
    public function __construct(
        private readonly SalesCapacityService $salesCapacity,
        private readonly CandidateRecommendationService $candidateRecommendations,
        private readonly SalaryMatrixService $salaryMatrix,
        private readonly InventoryCapacityService $inventoryCapacity,
    ) {}

    public function for(Game $game, ?string $department = null, ?string $role = null): array
    {
        $game->load(['company.employees.salaryEntries', 'company.employees.saleAttributions']);
        $company = $game->company;
        $employees = $company->employees;
        $activeEmployees = $employees->where('status', 'active');
        $pendingPayroll = $company->financialEntries()
            ->where('category', 'payroll')
            ->whereNull('paid_at')
            ->orderBy('due_date')
            ->get();
        $nextPayrollDate = $pendingPayroll->first()?->due_date;
        $daySeed = hexdec(substr(hash('sha256', "{$game->seed}:{$game->current_date->toDateString()}"), 0, 8));
        $capacity = $this->salesCapacity->calculate($game, $daySeed);
        $inventoryCapacity = $this->inventoryCapacity->calculate($company);

        return [
            'game' => [
                'id' => $game->id,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
                'victoryDays' => config('game.victory.days'),
                'status' => $game->status,
            ],
            'company' => ['id' => $company->id, 'name' => $company->name],
            'summary' => [
                'activeEmployees' => $activeEmployees->count(),
                'terminatedEmployees' => $employees->where('status', 'terminated')->count(),
                'monthlyPayrollCents' => (int) $activeEmployees->sum('monthly_salary_cents'),
                'nextPayrollDate' => $nextPayrollDate?->toDateString(),
                'nextPayrollCents' => $nextPayrollDate
                    ? (int) $pendingPayroll->where('due_date', $nextPayrollDate)->sum('amount_cents')
                    : 0,
                'commercialEmployees' => $capacity['commercial_employee_count'],
                'salesCapacityUnits' => $capacity['total_units'],
                'inventoryCapacityUnits' => $inventoryCapacity['capacity_units'],
                'inventoryUsedUnits' => $inventoryCapacity['used_units'],
                'logisticsEmployees' => $inventoryCapacity['logistics_employees'],
            ],
            'employees' => $employees
                ->sortBy([['status', 'asc'], ['name', 'asc']])
                ->map(fn ($employee) => [
                    'id' => $employee->id,
                    'populationNpcId' => $employee->population_npc_id,
                    'name' => $employee->name,
                    'department' => $employee->department,
                    'role' => $employee->role,
                    'monthlySalaryCents' => $employee->monthly_salary_cents,
                    'hiredOn' => $employee->hired_on->toDateString(),
                    'terminatedOn' => $employee->terminated_on?->toDateString(),
                    'status' => $employee->status,
                    'attributedUnitsSold' => (int) $employee->saleAttributions->sum('quantity'),
                    'attributedRevenueCents' => (int) $employee->saleAttributions->sum('revenue_cents'),
                    'nextSalaryDueDate' => $employee->salaryEntries
                        ->whereNull('paid_at')
                        ->sortBy('due_date')
                        ->first()?->due_date?->toDateString(),
                ])->values(),
            'options' => [
                'departments' => config('game.hr.departments'),
                'roles' => config('game.hr.roles'),
                'payrollDay' => config('game.hr.payroll_day'),
                'salaryMatrix' => $this->salaryMatrix->matrix(),
            ],
            'candidateSuggestions' => $department && $role
                ? $this->candidateRecommendations->recommend($game, $department, $role)
                : [],
        ];
    }
}
