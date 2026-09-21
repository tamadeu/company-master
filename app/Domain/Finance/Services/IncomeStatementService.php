<?php

namespace App\Domain\Finance\Services;

use App\Models\Company;
use Carbon\CarbonInterface;

class IncomeStatementService
{
    /** @return array<string, mixed> */
    public function calculate(Company $company, CarbonInterface $throughDate, ?CarbonInterface $fromDate = null): array
    {
        $periodStart = $fromDate ?? $throughDate->copy()->startOfYear()->max(config('game.initial_date'));
        $sales = $company->sales()->whereDate('game_date', '<=', $throughDate);
        $entries = $company->financialEntries()->whereDate('game_date', '<=', $throughDate);

        $sales->whereDate('game_date', '>=', $periodStart);
        $entries->whereDate('game_date', '>=', $periodStart);

        $grossRevenueCents = (int) $sales->sum('revenue_cents');
        $cogsCents = (int) (clone $sales)->sum('cogs_cents');
        $ledger = $entries->get(['type', 'category', 'amount_cents']);
        $sum = fn (array $categories, string $type = 'outflow'): int => (int) $ledger
            ->where('type', $type)
            ->whereIn('category', $categories)
            ->sum('amount_cents');
        $salesDeductionsCents = $sum(config('game.income_statement.sales_deduction_categories'));
        $netRevenueCents = $grossRevenueCents - $salesDeductionsCents;
        $grossProfitCents = $netRevenueCents - $cogsCents;
        $payrollCents = $sum(['payroll']);
        $fixedExpensesCents = $sum(['fixed_expense']);
        $maintenanceCents = $sum(['maintenance']);
        $otherOperatingExpensesCents = $sum(['operating_expense']);
        $operatingExpensesCents = $payrollCents + $fixedExpensesCents + $maintenanceCents + $otherOperatingExpensesCents;
        $ebitdaCents = $grossProfitCents - $operatingExpensesCents;
        $depreciationAndAmortizationCents = $sum(config('game.income_statement.depreciation_categories'));
        $operatingProfitCents = $ebitdaCents - $depreciationAndAmortizationCents;
        $financialIncomeCents = $sum(config('game.income_statement.financial_income_categories'), 'inflow');
        $financialExpensesCents = $sum(config('game.income_statement.financial_expense_categories'));
        $financialResultCents = $financialIncomeCents - $financialExpensesCents;
        $profitBeforeTaxCents = $operatingProfitCents + $financialResultCents;
        $incomeTaxCents = $sum(config('game.income_statement.income_tax_categories'));
        $netProfitCents = $profitBeforeTaxCents - $incomeTaxCents;
        $margin = fn (int $value): int => $netRevenueCents > 0 ? intdiv($value * 10_000, $netRevenueCents) : 0;

        return [
            'periodStart' => $periodStart->toDateString(),
            'periodEnd' => $throughDate->toDateString(),
            'grossRevenueCents' => $grossRevenueCents,
            'salesDeductionsCents' => $salesDeductionsCents,
            'netRevenueCents' => $netRevenueCents,
            'cogsCents' => $cogsCents,
            'grossProfitCents' => $grossProfitCents,
            'operatingExpenses' => [
                'payrollCents' => $payrollCents,
                'fixedExpensesCents' => $fixedExpensesCents,
                'maintenanceCents' => $maintenanceCents,
                'otherCents' => $otherOperatingExpensesCents,
            ],
            'operatingExpensesCents' => $operatingExpensesCents,
            'ebitdaCents' => $ebitdaCents,
            'depreciationAndAmortizationCents' => $depreciationAndAmortizationCents,
            'operatingProfitCents' => $operatingProfitCents,
            'financialIncomeCents' => $financialIncomeCents,
            'financialExpensesCents' => $financialExpensesCents,
            'financialResultCents' => $financialResultCents,
            'profitBeforeTaxCents' => $profitBeforeTaxCents,
            'incomeTaxCents' => $incomeTaxCents,
            'netProfitCents' => $netProfitCents,
            'grossMarginBasisPoints' => $margin($grossProfitCents),
            'ebitdaMarginBasisPoints' => $margin($ebitdaCents),
            'operatingMarginBasisPoints' => $margin($operatingProfitCents),
            'netMarginBasisPoints' => $margin($netProfitCents),
            'revenueCents' => $grossRevenueCents,
        ];
    }
}
