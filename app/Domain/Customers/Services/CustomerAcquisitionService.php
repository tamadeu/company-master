<?php

namespace App\Domain\Customers\Services;

use App\Models\Game;
use App\Models\SaleItem;

class CustomerAcquisitionService
{
    public function __construct(private readonly PopulationSelector $population) {}

    /** @return array{new_customers: int, customer_purchases: int} */
    public function attribute(Game $game, SaleItem $saleItem, int $quantity, int $seed): array
    {
        if (! $this->population->person($seed, 'population-availability')) {
            return ['new_customers' => 0, 'customer_purchases' => 0];
        }

        $quantitiesByNpc = [];
        for ($unit = 0; $unit < $quantity; $unit++) {
            $person = $this->population->person(
                $seed,
                "customer:{$game->current_date->toDateString()}:{$unit}",
            );
            $npcId = $person->id;
            $quantitiesByNpc[$npcId] = ($quantitiesByNpc[$npcId] ?? 0) + 1;
        }

        $newCustomers = 0;
        $newOrders = 0;
        foreach ($quantitiesByNpc as $npcId => $customerQuantity) {
            $customer = $game->company->customers()->firstOrCreate(
                ['population_npc_id' => $npcId],
                [
                    'acquired_on' => $game->current_date,
                    'last_purchase_on' => $game->current_date,
                    'purchase_count' => 0,
                    'lifetime_value_cents' => 0,
                    'status' => 'active',
                ],
            );
            $newCustomers += $customer->wasRecentlyCreated ? 1 : 0;
            $purchaseRevenueCents = $customerQuantity * $saleItem->unit_price_cents;
            $order = $saleItem->sale->customerOrders()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'company_id' => $game->company->id,
                    'game_date' => $game->current_date,
                    'status' => 'completed',
                    'total_quantity' => 0,
                    'revenue_cents' => 0,
                ],
            );
            $newOrders += $order->wasRecentlyCreated ? 1 : 0;

            $saleItem->customerPurchases()->create([
                'customer_order_id' => $order->id,
                'customer_id' => $customer->id,
                'quantity' => $customerQuantity,
                'revenue_cents' => $purchaseRevenueCents,
                'game_date' => $game->current_date,
            ]);
            $order->update([
                'total_quantity' => $order->total_quantity + $customerQuantity,
                'revenue_cents' => $order->revenue_cents + $purchaseRevenueCents,
            ]);
            $customer->update([
                'last_purchase_on' => $game->current_date,
                'purchase_count' => $customer->purchase_count + ($order->wasRecentlyCreated ? 1 : 0),
                'lifetime_value_cents' => $customer->lifetime_value_cents + $purchaseRevenueCents,
            ]);
        }

        return [
            'new_customers' => $newCustomers,
            'customer_purchases' => $newOrders,
        ];
    }
}
