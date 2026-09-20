<?php

namespace App\Domain\Hr\Services;

use App\Models\Game;

class CandidateRecommendationService
{
    public function __construct(private readonly SalaryMatrixService $salaryMatrix) {}

    /** @return array<int, array<string, int|string>> */
    public function recommend(Game $game, string $department, string $role, int $limit = 3): array
    {
        $salaryCents = $this->salaryMatrix->salaryCents($department, $role);
        $alreadyHiredNpcIds = $game->company->employees()
            ->whereNotNull('population_npc_id')
            ->pluck('population_npc_id');

        return $game->populationNpcs()
            ->whereNotIn('id', $alreadyHiredNpcIds)
            ->get()
            ->filter(fn ($npc) => abs((int) $npc->birth_date->diffInYears($game->current_date)) <= 65)
            ->sortBy(fn ($npc) => $this->score($game, $npc->code, $department, $role))
            ->take($limit)
            ->map(fn ($npc) => [
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

    private function score(Game $game, string $code, string $department, string $role): int
    {
        return hexdec(substr(hash(
            'sha256',
            "{$game->seed}:{$game->current_date->toDateString()}:{$department}:{$role}:{$code}",
        ), 0, 8));
    }
}
