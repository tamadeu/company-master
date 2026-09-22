<?php

namespace App\Domain\Game\Queries;

use App\Domain\Finance\Services\IncomeStatementService;
use App\Domain\Game\Services\GameDifficultyCatalog;
use App\Domain\Game\Services\OfficeLocationCatalog;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Number;

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
            'company.purchaseOrders.items',
            'company.purchaseOrders.supplier',
            'company.sales.items.product',
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
        $salesSince = $game->current_date->copy()->subDays(29);
        $salesByProduct = $company->sales
            ->filter(fn ($sale) => $sale->game_date->betweenIncluded($salesSince, $game->current_date))
            ->flatMap->items
            ->groupBy('product_id')
            ->map(fn ($items) => [
                'productId' => $items->first()->product_id,
                'productName' => $items->first()->product->name,
                'revenueCents' => $items->sum('revenue_cents'),
                'unitsSold' => $items->sum('quantity'),
            ])
            ->sortByDesc('revenueCents')
            ->values();
        $stockAlerts = $company->products
            ->map(function ($product) {
                $quantity = $product->inventoryBalances->first()?->quantity ?? 0;

                if ($quantity === 0) {
                    return [
                        'id' => "stockout-{$product->id}",
                        'type' => 'stockout',
                        'severity' => 'critical',
                        'title' => "Ruptura: {$product->name}",
                        'description' => 'Sem unidades disponíveis para atender vendas.',
                        'area' => 'inventory',
                    ];
                }

                if ($quantity <= $product->base_daily_demand) {
                    return [
                        'id' => "low-stock-{$product->id}",
                        'type' => 'low_stock',
                        'severity' => 'warning',
                        'title' => "Estoque baixo: {$product->name}",
                        'description' => "{$quantity} un. disponíveis para demanda base de {$product->base_daily_demand}/dia.",
                        'area' => 'inventory',
                    ];
                }

                return null;
            })
            ->filter()
            ->take(2);
        $problematicEvent = $activeEvent && in_array($activeEvent->type, ['heavy_rain', 'supplier_delay', 'emergency_maintenance'], true)
            ? [[
                'id' => "event-{$activeEvent->id}",
                'type' => 'event',
                'severity' => $activeEvent->type === 'emergency_maintenance' ? 'critical' : 'warning',
                'title' => $activeEvent->title,
                'description' => $activeEvent->description,
                'area' => $activeEvent->type === 'supplier_delay' ? 'purchases' : 'finance',
            ]]
            : [];
        $nextPayable = $company->financialEntries
            ->where('type', 'outflow')
            ->whereNull('paid_at')
            ->filter(fn ($entry) => $entry->due_date?->betweenIncluded($game->current_date, $nextWeek))
            ->sortBy('due_date')
            ->first();
        $financialAlerts = $nextPayable ? [[
            'id' => "payable-{$nextPayable->id}",
            'type' => 'payable',
            'severity' => 'warning',
            'title' => "Conta a pagar: {$nextPayable->description}",
            'description' => sprintf('%s vence em %s.', Number::currency($nextPayable->amount_cents / 100, 'BRL', 'pt_BR'), $nextPayable->due_date->format('d/m/Y')),
            'area' => 'finance',
        ]] : [];

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
            'upcomingDeliveries' => $company->purchaseOrders
                ->where('status', 'ordered')
                ->sortBy('expected_delivery_date')
                ->take(4)
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'supplierName' => $order->supplier->name,
                    'expectedDeliveryDate' => $order->expected_delivery_date->toDateString(),
                    'units' => $order->items->sum('quantity'),
                    'totalCents' => $order->total_cents,
                ])->values(),
            'salesByProduct' => $salesByProduct,
            'operationalAlerts' => $stockAlerts
                ->concat($problematicEvent)
                ->concat($financialAlerts)
                ->values(),
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
