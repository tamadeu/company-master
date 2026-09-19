<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Game> */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Partida '.fake()->unique()->numberBetween(1, 10_000),
            'status' => 'active',
            'seed' => fake()->numberBetween(1, 2_147_483_647),
            'current_date' => config('game.initial_date'),
            'started_at' => now(),
        ];
    }
}
