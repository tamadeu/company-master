<?php

namespace App\Console\Commands;

use App\Domain\Customers\Services\PopulationGenerator;
use Illuminate\Console\Command;

class GeneratePopulation extends Command
{
    protected $signature = 'population:generate {quantity : Number of people to create}';

    protected $description = 'Add fictional people to the global population';

    public function handle(PopulationGenerator $generator): int
    {
        $quantity = filter_var($this->argument('quantity'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1_000_000],
        ]);

        if ($quantity === false) {
            $this->error('A quantidade deve ser um número inteiro entre 1 e 1.000.000.');

            return self::FAILURE;
        }

        $this->info("Gerando {$quantity} pessoa(s)...");
        $created = $generator->generate($quantity);
        $this->info("{$created} pessoa(s) criada(s). População total: ".number_format($generator->count(), 0, ',', '.').'.');

        return self::SUCCESS;
    }
}
