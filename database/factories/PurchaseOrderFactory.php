<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'status' => 'ordered',
            'ordered_at_game_date' => config('game.initial_date'),
            'expected_delivery_date' => '2026-01-04',
            'total_cents' => 10_000,
        ];
    }
}
