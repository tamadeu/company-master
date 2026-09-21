<?php

namespace App\Domain\Game\Queries;

use App\Domain\Finance\Services\IncomeStatementService;
use App\Domain\Game\Services\GameDifficultyCatalog;
use App\Domain\Game\Services\OfficeLocationCatalog;
use App\Models\Game;
use App\Models\User;

class GameDashboardData
{
    public function __construct(
        private readonly IncomeStatementService $incomeStatement,
        private readonly OfficeLocationCatalog $officeLocations,
        private readonly GameDifficultyCatalog $difficulties,
    ) {}

    public function for(Game $game, User $user): array
    {
        $game->load([
            'company.products.inventoryBalances',
            'company.suppliers.products.product',
            'company.financialEntries',
            'company.sales',
            'dailySnapshots',
            'events',
        ]);

        $company = $game->company;
        $inventoryValueCents = $company->inventoryBalances->sum(
            fn ($balance) => $balance->quantity * $balance->average_cost_cents,
        );
        $nextWeek = $game->current_date->copy()->addDays(7);
        $payablesNextSevenDaysCents = $company->financialEntries
            ->where('type', 'outflow')
            ->whereNull('paid_at')
            ->filter(fn ($entry) => $entry->due_date?->betweenIncluded($game->current_date, $nextWeek))
            ->sum('amount_cents');
        $totalPayablesCents = $company->financialEntries
            ->where('type', 'outflow')
            ->whereNull('paid_at')
            ->sum('amount_cents');
        $incomeStatement = $this->incomeStatement->calculate($company, $game->current_date);
        $dayNumber = abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1;
        $activeEvent = $game->events
            ->where('status', 'active')
            ->first(fn ($event) => $event->starts_on->lessThanOrEqualTo($game->current_date)
                && $event->ends_on->greaterThanOrEqualTo($game->current_date));

        return [
            'games' => $user->games()
                ->with('company:id,game_id,name')
                ->latest()
                ->get()
                ->map(fn (Game $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'companyName' => $item->company->name,
                    'status' => $item->status,
                    'currentDate' => $item->current_date->toDateString(),
                ]),
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'status' => $game->status,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => $dayNumber,
                'victoryDays' => config('game.victory.days'),
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'officeLocationName' => $company->settings['office_location_name'] ?? 'Centro Empresarial',
                'difficultyName' => $company->settings['difficulty_name'] ?? 'Normal',
            ],
            'officeLocations' => $this->officeLocations->options(),
            'difficulties' => $this->difficulties->options(),
            'metrics' => [
                'cashBalanceCents' => $company->cash_balance_cents,
                'revenueCents' => $incomeStatement['revenueCents'],
                'netProfitCents' => $incomeStatement['netProfitCents'],
                'inventoryValueCents' => $inventoryValueCents,
                'payablesNextSevenDaysCents' => $payablesNextSevenDaysCents,
                'equityCents' => $company->cash_balance_cents + $inventoryValueCents - $totalPayablesCents,
                'victoryTargetCents' => config('game.victory.equity_cents'),
            ],
            'products' => $company->products->map(fn ($product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'salePriceCents' => $product->sale_price_cents,
                'baseDailyDemand' => $product->base_daily_demand,
                'stockQuantity' => $product->inventoryBalances->first()?->quantity ?? 0,
            ]),
            'suppliers' => $company->suppliers->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'profile' => $supplier->profile,
                'leadTimeDays' => $supplier->lead_time_days,
                'paymentTermDays' => $supplier->payment_term_days,
                'reliabilityPercent' => $supplier->reliability_percent,
                'lowestOfferCents' => $supplier->products->min('cost_cents'),
            ]),
            'mission' => [
                'stockPurchased' => $company->inventoryBalances->sum('quantity') > 0,
                'firstDayCompleted' => $dayNumber > 1,
            ],
            'tutorial' => [
                'completed' => (bool) ($company->settings['tutorial_completed'] ?? false),
            ],
            'manualAdvanceEnabled' => app()->environment('local'),
            'activeEvent' => $activeEvent ? [
                'type' => $activeEvent->type,
                'title' => $activeEvent->title,
                'description' => $activeEvent->description,
                'payload' => $activeEvent->payload,
                'startsOn' => $activeEvent->starts_on->toDateString(),
                'endsOn' => $activeEvent->ends_on->toDateString(),
            ] : null,
            'dailyHistory' => $game->dailySnapshots
                ->sortBy('game_date')
                ->take(-30)
                ->map(fn ($snapshot) => [
                    'date' => $snapshot->game_date->toDateString(),
                    'revenueCents' => $snapshot->sales_revenue_cents,
                    'cashChangeCents' => $snapshot->cash_change_cents,
                    'cashBalanceCents' => $snapshot->summary['cash_balance_cents'] ?? null,
                ])->values(),
        ];
    }
}
