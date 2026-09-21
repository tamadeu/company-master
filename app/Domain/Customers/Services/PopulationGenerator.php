<?php

namespace App\Domain\Customers\Services;

use App\Models\Employee;
use App\Models\PopulationNpc;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PopulationGenerator
{
    public function __construct(private readonly PopulationNameCatalog $names) {}

    public function generate(int $quantity): int
    {
        if ($quantity < 1) {
            return 0;
        }

        $seed = config('game.population.seed');
        $locations = config('game.population.locations');
        $initialDate = CarbonImmutable::parse(config('game.initial_date'));
        $now = now();
        $nextSequence = $this->nextSequence();
        $rows = [];

        for ($offset = 0; $offset < $quantity; $offset++) {
            $index = $nextSequence + $offset;
            $identity = $this->names->personForIndex($index);
            $name = $identity['name'];
            $location = $locations[$this->number($seed, "location:{$index}", 0, count($locations) - 1)];
            $age = $this->number($seed, "age:{$index}", 18, 75);
            $birthDate = $initialDate->subYears($age)->subDays($this->number($seed, "birth-day:{$index}", 0, 364));
            $code = sprintf('NPC-%04d', $index);

            $rows[] = [
                'code' => $code,
                'name' => $name,
                'birth_date' => $birthDate->toDateString(),
                'gender' => $identity['gender'],
                'city' => $location['city'],
                'state' => $location['state'],
                'email' => $this->email($name, $code),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('population_npcs')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('population_npcs')->insert($rows);
        }

        return $quantity;
    }

    public function count(): int
    {
        return PopulationNpc::query()->count();
    }

    public function refreshNames(int $chunkSize = 2_000, ?callable $progress = null): int
    {
        $processed = 0;

        PopulationNpc::query()
            ->select(['id', 'code', 'name', 'birth_date', 'gender', 'city', 'state', 'email', 'created_at'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($people) use (&$processed, $progress): void {
                $rows = [];
                $now = now();

                foreach ($people as $person) {
                    $processed++;
                    $identity = $this->names->personForIndex($processed);
                    $email = $this->email($identity['name'], $person->code);

                    if ($person->name === $identity['name'] && $person->gender === $identity['gender'] && $person->email === $email) {
                        continue;
                    }

                    $rows[] = [
                        'id' => $person->id,
                        'code' => $person->code,
                        'name' => $identity['name'],
                        'birth_date' => $person->birth_date,
                        'gender' => $identity['gender'],
                        'city' => $person->city,
                        'state' => $person->state,
                        'email' => $email,
                        'created_at' => $person->created_at,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('population_npcs')->upsert($rows, ['id'], ['name', 'gender', 'email', 'updated_at']);
                }
                $progress?->__invoke($processed);
            });

        Employee::query()
            ->whereNotNull('population_npc_id')
            ->with('populationNpc:id,name')
            ->chunkById($chunkSize, function ($employees): void {
                foreach ($employees as $employee) {
                    $employee->update(['name' => $employee->populationNpc->name]);
                }
            });

        return $processed;
    }

    private function nextSequence(): int
    {
        $maximumCode = PopulationNpc::query()
            ->where('code', 'like', 'NPC-%')
            ->pluck('code')
            ->reduce(function (int $maximum, string $code): int {
                return preg_match('/^NPC-(\d+)$/', $code, $matches)
                    ? max($maximum, (int) $matches[1])
                    : $maximum;
            }, 0);

        return max($maximumCode, $this->count()) + 1;
    }

    private function email(string $name, string $code): string
    {
        return Str::slug(Str::ascii($name), '.').'.'.strtolower($code).'@npc.erpgame.local';
    }

    private function number(int $seed, string $context, int $minimum, int $maximum): int
    {
        $value = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return $minimum + ($value % (($maximum - $minimum) + 1));
    }
}
