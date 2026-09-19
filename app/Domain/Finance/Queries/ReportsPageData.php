<?php

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Services\CashFlowService;
use App\Domain\Finance\Services\IncomeStatementService;
use App\Models\Game;
use App\Models\SaleItem;

class ReportsPageData
{
    public function __construct(
        private readonly CashFlowService $cashFlow,
        private readonly IncomeStatementService $incomeStatement,
    ) {}

    public function for(Game $game): array
    {
        $game->load([
            'dailySnapshots',
            'company.sales.items.product',
        ]);
        $company = $game->company;
        $saleItems = SaleItem::query()
            ->with('product')
            ->whereHas('sale', fn ($query) => $query->where('company_id', $company->id))
            ->get();

        return [
            'game' => [
                'id' => $game->id,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
                'victoryDays' => config('game.victory.days'),
            ],
            'company' => ['id' => $company->id, 'name' => $company->name],
            'incomeStatement' => $this->incomeStatement->calculate($company, $game->current_date),
            'cashFlow' => $this->cashFlow->daily($company, $game->current_date),
            'dailySnapshots' => $game->dailySnapshots->sortByDesc('game_date')->map(fn ($snapshot) => [
                'date' => $snapshot->game_date->toDateString(),
                'revenueCents' => $snapshot->sales_revenue_cents,
                'unitsSold' => $snapshot->units_sold,
                'cogsCents' => $snapshot->cogs_cents,
                'grossProfitCents' => $snapshot->summary['gross_profit_cents'],
                'expensesCents' => $snapshot->expenses_cents,
                'cashChangeCents' => $snapshot->cash_change_cents,
                'cashBalanceCents' => $snapshot->summary['cash_balance_cents'],
            ])->values(),
            'productSales' => $saleItems
                ->groupBy('product_id')
                ->map(fn ($items) => [
                    'productId' => $items->first()->product_id,
                    'productName' => $items->first()->product->name,
                    'unitsSold' => (int) $items->sum('quantity'),
                    'revenueCents' => (int) $items->sum('revenue_cents'),
                    'cogsCents' => (int) $items->sum('cogs_cents'),
                ])->sortByDesc('revenueCents')->values(),
        ];
    }
}
