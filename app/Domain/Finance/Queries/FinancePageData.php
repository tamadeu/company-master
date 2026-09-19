<?php

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Services\CashFlowService;
use App\Domain\Finance\Services\IncomeStatementService;
use App\Models\Game;

class FinancePageData
{
    public function __construct(
        private readonly CashFlowService $cashFlow,
        private readonly IncomeStatementService $incomeStatement,
    ) {}

    public function for(Game $game): array
    {
        $game->load(['company.financialEntries.settlement']);
        $company = $game->company;
        $entries = $company->financialEntries;
        $receivables = $entries->where('type', 'inflow')->whereNull('paid_at');
        $payables = $entries->where('type', 'outflow')->whereNull('paid_at');
        $overdue = $entries->whereNull('paid_at')->filter(
            fn ($entry) => $entry->due_date?->isBefore($game->current_date),
        );
        $nextWeek = $game->current_date->copy()->addDays(7);

        return [
            'game' => $this->gameData($game),
            'company' => ['id' => $company->id, 'name' => $company->name],
            'summary' => [
                'cashBalanceCents' => $company->cash_balance_cents,
                'receivablesCents' => (int) $receivables->sum('amount_cents'),
                'payablesCents' => (int) $payables->sum('amount_cents'),
                'overdueCents' => (int) $overdue->sum('amount_cents'),
                'nextSevenDaysCents' => (int) $entries->whereNull('paid_at')->filter(
                    fn ($entry) => $entry->due_date?->betweenIncluded($game->current_date, $nextWeek),
                )->sum('amount_cents'),
            ],
            'incomeStatement' => $this->incomeStatement->calculate($company, $game->current_date),
            'cashFlow' => $this->cashFlow->daily($company, $game->current_date),
            'entries' => $entries->sortByDesc('id')->take(100)->map(fn ($entry) => [
                'id' => $entry->id,
                'type' => $entry->type,
                'category' => $entry->category,
                'description' => $entry->description,
                'amountCents' => $entry->amount_cents,
                'gameDate' => $entry->game_date->toDateString(),
                'dueDate' => $entry->due_date?->toDateString(),
                'settledGameDate' => $entry->settled_game_date?->toDateString(),
                'status' => $entry->paid_at
                    ? 'settled'
                    : ($entry->due_date?->isBefore($game->current_date) ? 'overdue' : 'pending'),
            ])->values(),
        ];
    }

    private function gameData(Game $game): array
    {
        return [
            'id' => $game->id,
            'currentDate' => $game->current_date->toDateString(),
            'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
            'victoryDays' => config('game.victory.days'),
        ];
    }
}
