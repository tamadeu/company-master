<?php

namespace App\Domain\Sales\Queries;

use App\Models\CustomerOrder;
use App\Models\Game;

class CustomerOrderPageData
{
    public function for(Game $game, CustomerOrder $order): array
    {
        $order->load(['customer.populationNpc', 'purchases.saleItem.product']);

        return [
            'game' => [
                'id' => $game->id,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
                'victoryDays' => config('game.victory.days'),
            ],
            'company' => ['id' => $game->company->id, 'name' => $game->company->name],
            'order' => [
                'id' => $order->id,
                'number' => 'VEN-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
                'date' => $order->game_date->toDateString(),
                'status' => $order->status,
                'totalQuantity' => $order->total_quantity,
                'revenueCents' => $order->revenue_cents,
                'skuCount' => $order->purchases->count(),
                'createdAt' => $order->created_at->toIso8601String(),
                'customer' => [
                    'id' => $order->customer->id,
                    'name' => $order->customer->populationNpc->name,
                    'code' => $order->customer->populationNpc->code,
                    'email' => $order->customer->populationNpc->email,
                    'city' => $order->customer->populationNpc->city,
                    'state' => $order->customer->populationNpc->state,
                ],
                'items' => $order->purchases->sortBy('saleItem.product.name')->map(fn ($purchase) => [
                    'productId' => $purchase->saleItem->product_id,
                    'productName' => $purchase->saleItem->product->name,
                    'sku' => $purchase->saleItem->product->sku,
                    'quantity' => $purchase->quantity,
                    'unitPriceCents' => $purchase->saleItem->unit_price_cents,
                    'revenueCents' => $purchase->revenue_cents,
                ])->values(),
            ],
        ];
    }
}
