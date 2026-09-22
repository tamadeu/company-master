<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Inbox\Services\InboxService;
use App\Models\Game;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class CancelPurchaseOrder
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly InboxService $inbox,
    ) {}

    public function execute(Game $game, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($game, $purchaseOrder) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $company = $lockedGame->company()->lockForUpdate()->firstOrFail();
            $order = PurchaseOrder::query()
                ->with('financialEntry')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($order->company_id !== $company->id) {
                throw ValidationException::withMessages(['purchase_order' => 'Pedido inválido para esta partida.']);
            }

            if ($order->status !== 'ordered') {
                throw ValidationException::withMessages(['purchase_order' => 'Somente pedidos aguardando entrega podem ser cancelados.']);
            }

            $feeCents = intdiv($order->total_cents + 5, 10);
            $purchaseEntry = $order->financialEntry;

            if ($purchaseEntry?->paid_at) {
                $refund = $company->financialEntries()->create([
                    'type' => 'inflow',
                    'category' => 'purchase_refund',
                    'description' => "Estorno do pedido de compra #{$order->id}",
                    'amount_cents' => $order->total_cents,
                    'game_date' => $lockedGame->current_date,
                    'due_date' => $lockedGame->current_date,
                    'metadata' => ['purchase_order_id' => $order->id],
                ]);
                $this->ledger->settle($refund, $lockedGame->current_date);
            } else {
                $purchaseEntry?->delete();
            }

            $fee = $company->financialEntries()->create([
                'type' => 'outflow',
                'category' => 'purchase_cancellation_fee',
                'description' => "Multa de cancelamento do pedido #{$order->id}",
                'amount_cents' => $feeCents,
                'game_date' => $lockedGame->current_date,
                'due_date' => $lockedGame->current_date,
                'metadata' => ['purchase_order_id' => $order->id, 'rate_percent' => 10],
            ]);
            $this->ledger->settle($fee, $lockedGame->current_date);

            $order->update(['status' => 'cancelled']);
            $this->inbox->sendSystem(
                $lockedGame->user,
                'purchase',
                "Pedido de compra #{$order->id} cancelado",
                'O pedido foi cancelado com multa de 10% ('.Number::currency($feeCents / 100, 'BRL', 'pt_BR').').',
                "/games/{$lockedGame->id}/purchases",
                [
                    'order_id' => $order->id,
                    'cancellation_fee_cents' => $feeCents,
                    'game_date' => $lockedGame->current_date->toDateString(),
                ],
            );

            return $order->fresh(['supplier', 'items.product', 'financialEntry']);
        });
    }
}
