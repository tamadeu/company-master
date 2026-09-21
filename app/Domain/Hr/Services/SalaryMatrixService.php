<?php

namespace App\Domain\Hr\Services;

use App\Models\JobRole;
use Illuminate\Validation\ValidationException;

class SalaryMatrixService
{
    public function salaryCents(string $department, string $role): int
    {
        $salary = JobRole::query()
            ->where('department', $department)
            ->where('name', $role)
            ->where('active', true)
            ->value('salary_cents');

        if ($salary === null) {
            throw ValidationException::withMessages([
                'role' => 'Combinação de departamento e cargo inválida.',
            ]);
        }

        return (int) $salary;
    }

    public function matrix(): array
    {
        return JobRole::query()
            ->where('active', true)
            ->orderBy('department')
            ->orderBy('salary_cents')
            ->get()
            ->groupBy('department')
            ->map(fn ($roles) => $roles->pluck('salary_cents', 'name')->all())
            ->all();
    }
}
