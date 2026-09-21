<?php

namespace App\Jobs;

use App\Domain\Sales\Services\IdleSalesProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIdleSales implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 7_200;

    public function __construct(
        public readonly int $gameId,
        public readonly string $gameDate,
        public readonly string $tickKey,
        public readonly int $progressBasisPoints,
    ) {}

    public function handle(IdleSalesProcessor $processor): void
    {
        $processor->process($this->gameId, $this->gameDate, $this->tickKey, $this->progressBasisPoints);
    }

    public function uniqueId(): string
    {
        return "idle-sales:{$this->gameId}:{$this->gameDate}:{$this->tickKey}";
    }
}
