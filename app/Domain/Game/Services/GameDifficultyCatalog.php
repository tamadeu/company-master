<?php

namespace App\Domain\Game\Services;

use InvalidArgumentException;

class GameDifficultyCatalog
{
    /** @return array<string, int|string> */
    public function find(string $key): array
    {
        $difficulty = config("game.difficulties.{$key}");
        if (! is_array($difficulty)) {
            throw new InvalidArgumentException('Nível de dificuldade inválido.');
        }

        return ['key' => $key, ...$difficulty];
    }

    /** @return array<int, array<string, int|string>> */
    public function options(): array
    {
        return collect(config('game.difficulties'))
            ->map(fn (array $difficulty, string $key) => ['key' => $key, ...$difficulty])
            ->values()
            ->all();
    }
}
