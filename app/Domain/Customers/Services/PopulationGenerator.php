<?php

namespace App\Domain\Customers\Services;

use App\Models\PopulationNpc;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PopulationGenerator
{
    public function generate(): void
    {
        if (PopulationNpc::query()->exists()) {
            return;
        }

        $seed = config('game.population.seed');
        $firstNames = config('game.population.first_names');
        $lastNames = config('game.population.last_names');
        $locations = config('game.population.locations');
        $initialDate = CarbonImmutable::parse(config('game.initial_date'));
        $rows = [];
        $now = now();

        foreach (range(1, config('game.population.size')) as $index) {
            [$firstName, $gender] = $firstNames[$this->number($seed, "first-name:{$index}", 0, count($firstNames) - 1)];
            $firstLastName = $lastNames[($index - 1) % count($lastNames)];
            $secondLastName = $lastNames[$this->number($seed, "last-name:{$index}", 0, count($lastNames) - 1)];
            $name = "{$firstName} {$firstLastName} {$secondLastName}";
            $location = $locations[$this->number($seed, "location:{$index}", 0, count($locations) - 1)];
            $age = $this->number($seed, "age:{$index}", 18, 75);
            $birthDate = $initialDate->subYears($age)->subDays($this->number($seed, "birth-day:{$index}", 0, 364));
            $code = sprintf('NPC-%04d', $index);

            $rows[] = [
                'code' => $code,
                'name' => $name,
                'birth_date' => $birthDate->toDateString(),
                'gender' => $gender,
                'city' => $location['city'],
                'state' => $location['state'],
                'email' => Str::slug(Str::ascii($name), '.').'.'.strtolower($code).'@npc.erpgame.local',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('population_npcs')->insert($rows);
    }

    private function number(int $seed, string $context, int $minimum, int $maximum): int
    {
        $value = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return $minimum + ($value % (($maximum - $minimum) + 1));
    }
}
