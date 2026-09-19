<?php

namespace App\Domain\Finance\Services;

use App\Models\Company;
use Carbon\CarbonInterface;

class IncomeStatementService
{
    /** @return array<string, int> */
    public function calculate(Company $company, CarbonInterface $throughDate, ?CarbonInterface $fromDate = null): array
    {
        $sales = $company->sales()->whereDate('game_date', '<=', $throughDate);
        $expenses = $company->financialEntries()
            ->where('type', 'outflow')
            ->whereIn('category', config('game.income_statement.operating_expense_categories'))
            ->whereDate('game_date', '<=', $throughDate);

        if ($fromDate) {
            $sales->whereDate('game_date', '>=', $fromDate);
            $expenses->whereDate('game_date', '>=', $fromDate);
        }

        $revenueCents = (int) $sales->sum('revenue_cents');
        $cogsCents = (int) (clone $sales)->sum('cogs_cents');
        $grossProfitCents = $revenueCents - $cogsCents;
        $operatingExpensesCents = (int) $expenses->sum('amount_cents');

        return [
            'revenueCents' => $revenueCents,
            'cogsCents' => $cogsCents,
            'grossProfitCents' => $grossProfitCents,
            'operatingExpensesCents' => $operatingExpensesCents,
            'netProfitCents' => $grossProfitCents - $operatingExpensesCents,
            'grossMarginBasisPoints' => $revenueCents > 0
                ? intdiv($grossProfitCents * 10_000, $revenueCents)
                : 0,
        ];
    }
}
