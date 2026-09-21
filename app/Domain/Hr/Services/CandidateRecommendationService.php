<?php

namespace App\Domain\Hr\Services;

use App\Domain\Customers\Services\PopulationSelector;
use App\Models\Game;

class CandidateRecommendationService
{
    public function __construct(
        private readonly SalaryMatrixService $salaryMatrix,
        private readonly PopulationSelector $population,
    ) {}

    /** @return array<int, array<string, int|string>> */
    public function recommend(Game $game, string $department, string $role, int $limit = 3): array
    {
        $salaryCents = $this->salaryMatrix->salaryCents($department, $role);
        $alreadyHiredNpcIds = $game->company->employees()
            ->whereNotNull('population_npc_id')
            ->pluck('population_npc_id');

        $candidates = collect();
        $excludedIds = $alreadyHiredNpcIds->map(fn ($id) => (int) $id)->all();

        for ($attempt = 0; $candidates->count() < $limit && $attempt < 100; $attempt++) {
            $npc = $this->population->person(
                $game->seed,
                "candidate:{$game->current_date->toDateString()}:{$department}:{$role}:{$attempt}",
                $excludedIds,
            );

            if (! $npc) {
                break;
            }

            $excludedIds[] = $npc->id;
            if (abs((int) $npc->birth_date->diffInYears($game->current_date)) > 65) {
                continue;
            }

            $candidates->push($npc);
        }

        return $candidates->map(fn ($npc) => [
            'populationNpcId' => $npc->id,
            'code' => $npc->code,
            'name' => $npc->name,
            'age' => abs((int) $npc->birth_date->diffInYears($game->current_date)),
            'gender' => $npc->gender,
            'city' => $npc->city,
            'state' => $npc->state,
            'department' => $department,
            'role' => $role,
            'salaryCents' => $salaryCents,
        ])
            ->values()
            ->all();
    }
}
