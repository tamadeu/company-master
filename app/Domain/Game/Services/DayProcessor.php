<?php

namespace App\Domain\Game\Services;

use App\Domain\Finance\Services\SettleDuePayables;
use App\Domain\Finance\Services\SettleDueReceivables;
use App\Domain\Inbox\Services\InboxService;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Sales\Services\SalesSimulator;
use App\Models\DailySnapshot;
use App\Models\DayProcess;
use App\Models\Game;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DayProcessor
{
    public function __construct(
        private readonly ReceivePurchaseOrder $receivePurchaseOrder,
        private readonly SettleDueReceivables $settleDueReceivables,
        private readonly SettleDuePayables $settleDuePayables,
        private readonly SalesSimulator $salesSimulator,
        private readonly EventEngine $eventEngine,
        private readonly GameOutcomeService $outcomes,
        private readonly InboxService $inbox,
    ) {}

    public function process(Game $game, string $expectedDate): array
    {
        return DB::transaction(function () use ($game, $expectedDate) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $existingProcess = DayProcess::query()
                ->where('game_id', $lockedGame->id)
                ->whereDate('game_date', $expectedDate)
                ->lockForUpdate()
                ->first();

            if ($existingProcess?->status === 'completed') {
                return DailySnapshot::query()
                    ->where('game_id', $lockedGame->id)
                    ->whereDate('game_date', $expectedDate)
                    ->firstOrFail()
                    ->summary;
            }

            if ($lockedGame->status !== 'active') {
                throw ValidationException::withMessages(['game' => 'A partida não está ativa.']);
            }

            if ($lockedGame->current_date->toDateString() !== $expectedDate) {
                throw ValidationException::withMessages(['game_date' => 'A data informada não é a data atual da partida.']);
            }

            $seedUsed = $this->daySeed($lockedGame->seed, $expectedDate);
            $process = DayProcess::query()->updateOrCreate(
                ['game_id' => $lockedGame->id, 'game_date' => $expectedDate],
                ['status' => 'processing', 'seed_used' => $seedUsed, 'error' => null],
            );
            $company = $lockedGame->company()->lockForUpdate()->firstOrFail();
            $cashBeforeCents = $company->cash_balance_cents;
            $receivedOrderIds = [];

            $orders = PurchaseOrder::query()
                ->where('company_id', $company->id)
                ->where('status', 'ordered')
                ->whereDate('expected_delivery_date', '<=', $lockedGame->current_date)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $this->receivePurchaseOrder->execute($lockedGame, $order);
                $receivedOrderIds[] = $order->id;
            }

            $company->refresh();
            $receipts = $this->settleDueReceivables->settle($company, $lockedGame->current_date);
            $company->refresh();
            $payments = $this->settleDuePayables->settle($company, $lockedGame->current_date);
            $company->refresh();
            $event = null;

            if ($payments['insufficient']) {
                $sales = [
                    'sale_id' => 0,
                    'revenue_cents' => 0,
                    'cogs_cents' => 0,
                    'units_sold' => 0,
                    'stockout_product_ids' => [],
                    'commercial_capacity_units' => 0,
                    'unmet_demand_units' => 0,
                    'new_customers' => 0,
                    'customer_purchases' => 0,
                ];
            } else {
                $eventEffects = $this->eventEngine->activeEffects($lockedGame->setRelation('company', $company));
                $sales = $this->salesSimulator->simulate($lockedGame, $seedUsed, $eventEffects);
                $company->refresh();
                $lockedGame->setRelation('company', $company);
                $event = $this->eventEngine->trigger($lockedGame, $seedUsed);
            }

            $company->refresh();
            $inventoryValueCents = (int) $company->inventoryBalances()->get()->sum(
                fn ($balance) => $balance->quantity * $balance->average_cost_cents,
            );
            $completedDays = abs((int) $lockedGame->current_date->diffInDays(config('game.initial_date'))) + 1;
            $outcome = $this->outcomes->evaluate($lockedGame->setRelation('company', $company), $completedDays);
            $gameUrl = "/games/{$lockedGame->id}";

            if ($sales['units_sold'] > 0) {
                $this->inbox->sendSystem(
                    $lockedGame->user,
                    'sale',
                    "{$sales['units_sold']} unidade(s) vendida(s)",
                    "As vendas de {$expectedDate} foram processadas para {$company->name}.",
                    "/games/{$lockedGame->id}/products",
                    [
                        'game_id' => $lockedGame->id,
                        'game_name' => $lockedGame->name,
                        'company_name' => $company->name,
                        'game_date' => $expectedDate,
                        'revenue_cents' => $sales['revenue_cents'],
                        'units_sold' => $sales['units_sold'],
                        'new_customers' => $sales['new_customers'],
                    ],
                );
            }

            if ($receivedOrderIds !== []) {
                $count = count($receivedOrderIds);
                $this->inbox->sendSystem(
                    $lockedGame->user,
                    'operation',
                    'Mercadoria recebida',
                    $count === 1 ? 'Um pedido de compra foi recebido e incorporado ao estoque.' : "{$count} pedidos de compra foram recebidos e incorporados ao estoque.",
                    $gameUrl,
                );
            }

            if ($payments['insufficient']) {
                $this->inbox->sendSystem(
                    $lockedGame->user,
                    'alert',
                    'Caixa insuficiente',
                    'A empresa não conseguiu liquidar todas as obrigações do dia. Revise o financeiro antes de avançar novamente.',
                    $gameUrl,
                );
            }

            if ($event) {
                $this->inbox->sendSystem(
                    $lockedGame->user,
                    'event',
                    $event->title,
                    $event->description,
                    $gameUrl,
                );
            }

            if ($outcome['status'] !== 'active') {
                $this->inbox->sendSystem(
                    $lockedGame->user,
                    'outcome',
                    $outcome['status'] === 'won' ? 'Objetivo alcançado' : 'Partida encerrada por falência',
                    $outcome['status'] === 'won'
                        ? 'Sua empresa atingiu as condições de vitória. Confira o resultado final.'
                        : 'A empresa não conseguiu manter suas obrigações. Confira o resultado final da partida.',
                    $gameUrl,
                );
            }
            $eventExpenseCents = $event?->type === 'emergency_maintenance' && $event->payload['settled']
                ? $event->payload['amount_cents']
                : 0;
            $cashChangeCents = $company->cash_balance_cents - $cashBeforeCents;
            $summary = [
                'processed_date' => $expectedDate,
                'next_date' => $lockedGame->current_date->copy()->addDay()->toDateString(),
                'sales_revenue_cents' => $sales['revenue_cents'],
                'units_sold' => $sales['units_sold'],
                'cogs_cents' => $sales['cogs_cents'],
                'gross_profit_cents' => $sales['revenue_cents'] - $sales['cogs_cents'],
                'expenses_cents' => $payments['amount_cents'] + $eventExpenseCents,
                'receipts_cents' => $receipts['amount_cents'],
                'cash_change_cents' => $cashChangeCents,
                'cash_balance_cents' => $company->cash_balance_cents,
                'inventory_value_cents' => $inventoryValueCents,
                'stockout_product_ids' => $sales['stockout_product_ids'],
                'commercial_capacity_units' => $sales['commercial_capacity_units'],
                'unmet_demand_units' => $sales['unmet_demand_units'],
                'new_customers' => $sales['new_customers'],
                'customer_purchases' => $sales['customer_purchases'],
                'received_purchase_order_ids' => $receivedOrderIds,
                'event' => $event ? [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'description' => $event->description,
                    'payload' => $event->payload,
                    'starts_on' => $event->starts_on->toDateString(),
                    'ends_on' => $event->ends_on->toDateString(),
                ] : null,
                'game_status' => $outcome['status'],
                'equity_cents' => $outcome['equity_cents'],
            ];

            DailySnapshot::query()->create([
                'game_id' => $lockedGame->id,
                'game_date' => $expectedDate,
                'sales_revenue_cents' => $sales['revenue_cents'],
                'units_sold' => $sales['units_sold'],
                'cogs_cents' => $sales['cogs_cents'],
                'expenses_cents' => $payments['amount_cents'] + $eventExpenseCents,
                'cash_change_cents' => $cashChangeCents,
                'inventory_value_cents' => $inventoryValueCents,
                'summary' => $summary,
            ]);
            $process->update(['status' => 'completed']);
            $lockedGame->update([
                'current_date' => $lockedGame->current_date->copy()->addDay(),
                'last_processed_at' => now(),
                'next_processing_at' => $outcome['status'] === 'active' && $lockedGame->automation_enabled
                    ? now()->addMinutes(config('game.automation.interval_minutes'))
                    : null,
            ]);

            return $summary;
        }, attempts: 3);
    }

    public function daySeed(int $gameSeed, string $gameDate): int
    {
        return hexdec(substr(hash('sha256', "{$gameSeed}:{$gameDate}"), 0, 8));
    }
}
