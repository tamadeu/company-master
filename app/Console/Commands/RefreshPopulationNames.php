<?php

namespace App\Console\Commands;

use App\Domain\Customers\Services\PopulationGenerator;
use Illuminate\Console\Command;

class RefreshPopulationNames extends Command
{
    protected $signature = 'population:refresh-names {--chunk=2000 : Records updated per database batch}';

    protected $description = 'Regenerate names, genders and internal emails for the existing population';

    public function handle(PopulationGenerator $generator): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 100, 'max_range' => 5_000],
        ]);

        if ($chunkSize === false) {
            $this->error('O lote deve ser um número inteiro entre 100 e 5.000.');

            return self::FAILURE;
        }

        $total = $generator->count();
        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $updated = $generator->refreshNames($chunkSize, fn (int $processed) => $progress->setProgress($processed));

        $progress->finish();
        $this->newLine(2);
        $this->info(number_format($updated, 0, ',', '.').' pessoa(s) atualizada(s).');

        return self::SUCCESS;
    }
}
