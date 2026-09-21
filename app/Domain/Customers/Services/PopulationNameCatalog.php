<?php

namespace App\Domain\Customers\Services;

use Illuminate\Support\Str;
use RuntimeException;

class PopulationNameCatalog
{
    /** @var array<int, array{0: string, 1: string}>|null */
    private ?array $firstNames = null;

    /** @var array<int, string>|null */
    private ?array $lastNames = null;

    /** @return array{name: string, gender: string} */
    public function personForIndex(int $index): array
    {
        $firstNames = $this->firstNames();
        $lastNames = $this->lastNames();
        $firstNameCount = count($firstNames);
        $lastNameCount = count($lastNames);
        $capacity = $firstNameCount * $lastNameCount * ($lastNameCount - 1);

        if ($index < 1 || $index > $capacity) {
            throw new RuntimeException("O catálogo suporta índices entre 1 e {$capacity}.");
        }

        $slot = $index - 1;
        [$firstName, $gender] = $firstNames[$slot % $firstNameCount];
        $lastNameSlot = intdiv($slot, $firstNameCount);
        $firstLastNameIndex = $lastNameSlot % $lastNameCount;
        $secondLastNameIndex = intdiv($lastNameSlot, $lastNameCount) % ($lastNameCount - 1);

        if ($secondLastNameIndex >= $firstLastNameIndex) {
            $secondLastNameIndex++;
        }

        return [
            'name' => "{$firstName} {$lastNames[$firstLastNameIndex]} {$lastNames[$secondLastNameIndex]}",
            'gender' => $gender,
        ];
    }

    public function capacity(): int
    {
        return count($this->firstNames()) * count($this->lastNames()) * (count($this->lastNames()) - 1);
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function firstNames(): array
    {
        if ($this->firstNames !== null) {
            return $this->firstNames;
        }

        $names = $this->readLines(resource_path('data/population/nomes.csv'));
        $masculine = false;
        $seen = [];
        $catalog = [];

        foreach ($names as $name) {
            if ($name === 'Santiago') {
                $masculine = true;
            }

            $key = $this->key($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $catalog[] = [$name, $masculine ? 'Masculino' : 'Feminino'];
        }

        if (! $masculine || $catalog === []) {
            throw new RuntimeException('A base de primeiros nomes é inválida.');
        }

        return $this->firstNames = $catalog;
    }

    /** @return array<int, string> */
    private function lastNames(): array
    {
        if ($this->lastNames !== null) {
            return $this->lastNames;
        }

        $seen = [];
        $catalog = [];

        foreach ($this->readLines(resource_path('data/population/apelidos.csv')) as $name) {
            $key = $this->key($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $catalog[] = $name;
        }

        if (count($catalog) < 2) {
            throw new RuntimeException('A base de apelidos é inválida.');
        }

        return $this->lastNames = $catalog;
    }

    /** @return array<int, string> */
    private function readLines(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException("Não foi possível ler {$path}.");
        }

        return array_values(array_filter(array_map(
            fn (string $line) => trim($line, "\xEF\xBB\xBF \t\n\r\0\x0B"),
            $lines,
        )));
    }

    private function key(string $name): string
    {
        return mb_strtolower(Str::ascii($name));
    }
}
