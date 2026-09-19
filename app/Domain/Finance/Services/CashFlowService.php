<?php

namespace App\Domain\Finance\Services;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class CashFlowService
{
    /** @return array<int, array{date: string, inflowsCents: int, outflowsCents: int, netCents: int, balanceCents: int}> */
    public function daily(Company $company, CarbonInterface $throughDate, int $days = 30): array
    {
        $initialDate = CarbonImmutable::parse(config('game.initial_date'));
        $startDate = CarbonImmutable::instance($throughDate)->subDays($days - 1);
        if ($startDate->isBefore($initialDate)) {
            $startDate = $initialDate;
        }

        $settlements = $company->financialSettlements()
            ->whereDate('game_date', '<=', $throughDate)
            ->orderBy('game_date')
            ->get();
        $openingBalanceCents = (int) $settlements
            ->filter(fn ($settlement) => $settlement->game_date->isBefore($startDate))
            ->sum(fn ($settlement) => $settlement->type === 'inflow' ? $settlement->amount_cents : -$settlement->amount_cents);
        $settlementsByDate = $settlements
            ->filter(fn ($settlement) => $settlement->game_date->greaterThanOrEqualTo($startDate))
            ->groupBy(fn ($settlement) => $settlement->game_date->toDateString());
        $balanceCents = $openingBalanceCents;
        $result = [];

        foreach (CarbonPeriod::create($startDate, $throughDate) as $date) {
            $daySettlements = $settlementsByDate->get($date->toDateString(), collect());
            $inflowsCents = (int) $daySettlements->where('type', 'inflow')->sum('amount_cents');
            $outflowsCents = (int) $daySettlements->where('type', 'outflow')->sum('amount_cents');
            $netCents = $inflowsCents - $outflowsCents;
            $balanceCents += $netCents;
            $result[] = [
                'date' => $date->toDateString(),
                'inflowsCents' => $inflowsCents,
                'outflowsCents' => $outflowsCents,
                'netCents' => $netCents,
                'balanceCents' => $balanceCents,
            ];
        }

        return $result;
    }
}
