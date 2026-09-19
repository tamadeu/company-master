<?php

namespace App\Domain\Game\Services;

class DemandCalculator
{
    public function calculate(
        int $baseDailyDemand,
        int $referencePriceCents,
        int $salePriceCents,
        int $seed,
        string $context,
        int $eventFactorBasisPoints = 10_000,
    ): int {
        $priceFactor = $this->priceFactorBasisPoints($referencePriceCents, $salePriceCents);
        $randomFactor = $this->randomFactorBasisPoints($seed, $context);
        $denominator = 100_000_000;
        $numerator = $baseDailyDemand * $priceFactor * $randomFactor * $eventFactorBasisPoints;

        return intdiv($numerator + ($denominator * 5_000), $denominator * 10_000);
    }

    public function priceFactorBasisPoints(int $referencePriceCents, int $salePriceCents): int
    {
        if ($salePriceCents <= 0) {
            return 18_000;
        }

        return max(3_500, min(18_000, intdiv($referencePriceCents * 10_000, $salePriceCents)));
    }

    public function randomFactorBasisPoints(int $seed, string $context): int
    {
        $hashValue = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return 8_500 + ($hashValue % 3_001);
    }
}
