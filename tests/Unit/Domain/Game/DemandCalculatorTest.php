<?php

use App\Domain\Game\Services\DemandCalculator;

test('demand decreases when price increases with the same seed', function () {
    $calculator = new DemandCalculator;

    $lowPriceDemand = $calculator->calculate(100, 2_000, 1_500, 42, '2026-01-01:1');
    $highPriceDemand = $calculator->calculate(100, 2_000, 4_000, 42, '2026-01-01:1');

    expect($lowPriceDemand)->toBeGreaterThan($highPriceDemand);
});

test('price and random factors respect their configured limits', function () {
    $calculator = new DemandCalculator;

    expect($calculator->priceFactorBasisPoints(1_000, 100_000))->toBe(3_500)
        ->and($calculator->priceFactorBasisPoints(100_000, 1_000))->toBe(18_000);

    foreach (range(1, 100) as $seed) {
        expect($calculator->randomFactorBasisPoints($seed, 'product'))->toBeBetween(8_500, 11_500);
    }
});

test('the same seed and context always produce the same demand', function () {
    $calculator = new DemandCalculator;

    $first = $calculator->calculate(25, 3_490, 3_490, 20260101, '2026-01-01:10');
    $second = $calculator->calculate(25, 3_490, 3_490, 20260101, '2026-01-01:10');

    expect($first)->toBe($second);
});
