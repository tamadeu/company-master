<?php

namespace App\Domain\Game\Services;

use InvalidArgumentException;

class OfficeLocationCatalog
{
    /** @return array<string, int|string> */
    public function find(string $key): array
    {
        $location = config("game.office_locations.{$key}");
        if (! is_array($location)) {
            throw new InvalidArgumentException('Localização de escritório inválida.');
        }

        return ['key' => $key, ...$location];
    }

    /** @return array<int, array<string, int|string>> */
    public function options(): array
    {
        return collect(config('game.office_locations'))
            ->map(fn (array $location, string $key) => ['key' => $key, ...$location])
            ->values()
            ->all();
    }
}
