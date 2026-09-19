<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $priceCents = fake()->numberBetween(1_000, 10_000);

        return [
            'company_id' => Company::factory(),
            'sku' => fake()->unique()->bothify('???-###'),
            'name' => fake()->words(3, true),
            'sale_price_cents' => $priceCents,
            'reference_price_cents' => $priceCents,
            'base_daily_demand' => fake()->numberBetween(1, 30),
        ];
    }
}
