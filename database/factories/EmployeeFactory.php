<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'department' => fake()->randomElement(config('game.hr.departments')),
            'role' => fake()->randomElement(config('game.hr.roles')),
            'monthly_salary_cents' => fake()->numberBetween(180_000, 800_000),
            'hired_on' => config('game.initial_date'),
            'status' => 'active',
        ];
    }
}
