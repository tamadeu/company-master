<?php

namespace App\Domain\Hr\Services;

use Illuminate\Validation\ValidationException;

class SalaryMatrixService
{
    public function salaryCents(string $department, string $role): int
    {
        $salary = config("game.hr.salary_matrix_cents.{$department}.{$role}");
        if (! is_int($salary)) {
            throw ValidationException::withMessages([
                'role' => 'Combinação de departamento e cargo inválida.',
            ]);
        }

        return $salary;
    }

    public function matrix(): array
    {
        return config('game.hr.salary_matrix_cents');
    }
}
