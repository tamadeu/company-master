<?php

namespace Database\Seeders;

use App\Domain\Game\Actions\CreateGame;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(CreateGame $createGame): void
    {
        User::factory()->create([
            'name' => 'Jogador Demo',
            'email' => 'demo@erpgame.local',
        ]);

        $createGame->execute(
            User::where('email', 'demo@erpgame.local')->firstOrFail(),
            'Primeiros passos',
            'Mercado Aurora',
            20260101,
        );
    }
}
