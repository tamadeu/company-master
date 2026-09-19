<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->company(),
            'profile' => 'Equilibrado',
            'lead_time_days' => 3,
            'payment_term_days' => 7,
            'reliability_percent' => 95,
        ];
    }
}
