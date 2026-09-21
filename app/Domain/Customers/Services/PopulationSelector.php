<?php

namespace App\Domain\Customers\Services;

use App\Models\PopulationNpc;

class PopulationSelector
{
    /** @var array{minimum: int, maximum: int}|null */
    private ?array $bounds = null;

    /** @param array<int, int> $excludedIds */
    public function person(int $seed, string $context, array $excludedIds = []): ?PopulationNpc
    {
        $bounds = $this->bounds();
        if ($bounds === null) {
            return null;
        }

        $candidateId = $this->number($seed, $context, $bounds['minimum'], $bounds['maximum']);
        $query = PopulationNpc::query()->when($excludedIds, fn ($query) => $query->whereNotIn('id', $excludedIds));
        $person = (clone $query)->where('id', '>=', $candidateId)->orderBy('id')->first();

        return $person ?? $query->orderBy('id')->first();
    }

    /** @return array{minimum: int, maximum: int}|null */
    private function bounds(): ?array
    {
        if ($this->bounds !== null) {
            return $this->bounds;
        }

        $minimum = PopulationNpc::query()->min('id');
        if ($minimum === null) {
            return null;
        }

        return $this->bounds = [
            'minimum' => (int) $minimum,
            'maximum' => (int) PopulationNpc::query()->max('id'),
        ];
    }

    private function number(int $seed, string $context, int $minimum, int $maximum): int
    {
        $value = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return $minimum + ($value % (($maximum - $minimum) + 1));
    }
}
