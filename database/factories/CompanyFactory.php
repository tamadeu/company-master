<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->company(),
            'cash_balance_cents' => config('game.initial_capital_cents'),
            'settings' => [],
        ];
    }
}
