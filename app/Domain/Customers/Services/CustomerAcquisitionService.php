<?php

namespace App\Domain\Customers\Services;

use App\Models\Game;
use App\Models\SaleItem;

class CustomerAcquisitionService
{
    /** @return array{new_customers: int, customer_purchases: int} */
    public function attribute(Game $game, SaleItem $saleItem, int $quantity, int $seed): array
    {
        $populationIds = $game->populationNpcs()->orderBy('id')->pluck('id')->all();
        if ($populationIds === []) {
            return ['new_customers' => 0, 'customer_purchases' => 0];
        }

        $quantitiesByNpc = [];
        for ($unit = 0; $unit < $quantity; $unit++) {
            $populationIndex = $this->number(
                $seed,
                "customer:{$game->current_date->toDateString()}:{$saleItem->product_id}:{$unit}",
                0,
                count($populationIds) - 1,
            );
            $npcId = $populationIds[$populationIndex];
            $quantitiesByNpc[$npcId] = ($quantitiesByNpc[$npcId] ?? 0) + 1;
        }

        $newCustomers = 0;
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

            $saleItem->customerPurchases()->create([
                'customer_id' => $customer->id,
                'quantity' => $customerQuantity,
                'revenue_cents' => $purchaseRevenueCents,
                'game_date' => $game->current_date,
            ]);
            $customer->update([
                'last_purchase_on' => $game->current_date,
                'purchase_count' => $customer->purchase_count + 1,
                'lifetime_value_cents' => $customer->lifetime_value_cents + $purchaseRevenueCents,
            ]);
        }

        return [
            'new_customers' => $newCustomers,
            'customer_purchases' => count($quantitiesByNpc),
        ];
    }

    private function number(int $seed, string $context, int $minimum, int $maximum): int
    {
        $value = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return $minimum + ($value % (($maximum - $minimum) + 1));
    }
}
