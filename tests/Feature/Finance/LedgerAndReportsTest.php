<?php

use App\Domain\Finance\Services\CashFlowService;
use App\Domain\Finance\Services\IncomeStatementService;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\SettleDuePayables;
use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\FinancialSettlement;
use App\Models\User;
use Carbon\CarbonImmutable;

test('settling an entry changes cash once and creates an immutable ledger record', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Financeiro', 'Mercado Aurora', 700);
    $company = $game->company->fresh();
    $entry = $company->financialEntries()->create([
        'type' => 'inflow',
        'category' => 'receivable',
        'description' => 'Recebível de teste',
        'amount_cents' => 50_000,
        'game_date' => $game->current_date,
        'due_date' => $game->current_date,
    ]);

    $first = app(LedgerService::class)->settle($entry, $game->current_date);
    $second = app(LedgerService::class)->settle($entry, $game->current_date);

    expect($second->id)->toBe($first->id)
        ->and($company->fresh()->cash_balance_cents)->toBe(10_050_000)
        ->and(FinancialSettlement::where('financial_entry_id', $entry->id)->count())->toBe(1)
        ->and(fn () => $first->update(['amount_cents' => 1]))->toThrow(LogicException::class);
});

test('due receivables are collected before due payables', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Contas', 'Mercado Aurora', 701);
    $company = $game->company;
    $company->update(['cash_balance_cents' => 0]);
    $company->financialEntries()->create([
        'type' => 'inflow', 'category' => 'receivable', 'description' => 'Cliente',
        'amount_cents' => 2_000, 'game_date' => $game->current_date, 'due_date' => $game->current_date,
    ]);
    $company->financialEntries()->create([
        'type' => 'outflow', 'category' => 'operating_expense', 'description' => 'Conta',
        'amount_cents' => 1_500, 'game_date' => $game->current_date, 'due_date' => $game->current_date,
    ]);

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($summary['receipts_cents'])->toBe(2_000)
        ->and($summary['expenses_cents'])->toBe(1_500)
        ->and($company->fresh()->cash_balance_cents)->toBe(500);
});

test('income statement separates purchases from operating expenses', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'DRE', 'Mercado Aurora', 702);
    $company = $game->company;
    $company->financialEntries()->create([
        'type' => 'outflow', 'category' => 'purchase', 'description' => 'Compra',
        'amount_cents' => 99_000, 'game_date' => $game->current_date, 'due_date' => $game->current_date,
    ]);
    $company->financialEntries()->create([
        'type' => 'outflow', 'category' => 'operating_expense', 'description' => 'Operação',
        'amount_cents' => 2_500, 'game_date' => $game->current_date, 'due_date' => $game->current_date,
    ]);

    $statement = app(IncomeStatementService::class)->calculate($company, $game->current_date);

    expect($statement['operatingExpensesCents'])->toBe(2_500)
        ->and($statement['netProfitCents'])->toBe(-2_500);
});

test('income statement presents complete subtotals and margins by competence', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'DRE completa', 'Mercado Aurora', 705);
    $company = $game->company;
    $company->sales()->create([
        'game_date' => $game->current_date,
        'status' => 'completed',
        'revenue_cents' => 100_000,
        'cogs_cents' => 40_000,
    ]);
    foreach ([
        ['outflow', 'sales_deduction', 2_000],
        ['outflow', 'payroll', 10_000],
        ['outflow', 'fixed_expense', 5_000],
        ['outflow', 'maintenance', 2_000],
        ['outflow', 'operating_expense', 3_000],
        ['outflow', 'depreciation', 4_000],
        ['inflow', 'financial_income', 1_000],
        ['outflow', 'financial_expense', 2_500],
        ['outflow', 'income_tax', 500],
    ] as [$type, $category, $amount]) {
        $company->financialEntries()->create([
            'type' => $type,
            'category' => $category,
            'description' => $category,
            'amount_cents' => $amount,
            'game_date' => $game->current_date,
        ]);
    }

    $statement = app(IncomeStatementService::class)->calculate($company, $game->current_date);

    expect($statement)->toMatchArray([
        'grossRevenueCents' => 100_000,
        'salesDeductionsCents' => 2_000,
        'netRevenueCents' => 98_000,
        'cogsCents' => 40_000,
        'grossProfitCents' => 58_000,
        'operatingExpensesCents' => 20_000,
        'ebitdaCents' => 38_000,
        'depreciationAndAmortizationCents' => 4_000,
        'operatingProfitCents' => 34_000,
        'financialIncomeCents' => 1_000,
        'financialExpensesCents' => 2_500,
        'financialResultCents' => -1_500,
        'profitBeforeTaxCents' => 32_500,
        'incomeTaxCents' => 500,
        'netProfitCents' => 32_000,
        'grossMarginBasisPoints' => 5_918,
        'ebitdaMarginBasisPoints' => 3_877,
        'operatingMarginBasisPoints' => 3_469,
        'netMarginBasisPoints' => 3_265,
    ])->and($statement['operatingExpenses'])->toBe([
        'payrollCents' => 10_000,
        'fixedExpensesCents' => 5_000,
        'maintenanceCents' => 2_000,
        'otherCents' => 3_000,
    ]);
});

test('cash flow is derived from immutable settlements', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Fluxo', 'Mercado Aurora', 703);
    $flow = app(CashFlowService::class)->daily(
        $game->company->fresh(),
        CarbonImmutable::parse('2026-01-02'),
    );

    expect($flow)->toHaveCount(2)
        ->and($flow[0]['inflowsCents'])->toBe(10_000_000)
        ->and($flow[0]['balanceCents'])->toBe(10_000_000)
        ->and($flow[1]['balanceCents'])->toBe(10_000_000);
});

test('paying a recurring expense schedules the next month once', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Recorrência', 'Mercado Aurora', 704);
    $company = $game->company;
    $januaryRent = $company->financialEntries()->where('description', 'Aluguel')->firstOrFail();

    app(SettleDuePayables::class)->settle($company, CarbonImmutable::parse('2026-01-05'));
    app(SettleDuePayables::class)->settle($company->fresh(), CarbonImmutable::parse('2026-01-05'));

    expect($januaryRent->fresh()->settled_game_date->toDateString())->toBe('2026-01-05')
        ->and($company->financialEntries()->where('description', 'Aluguel')->count())->toBe(2)
        ->and($company->financialEntries()->whereDate('due_date', '2026-02-05')->count())->toBe(1);
});
