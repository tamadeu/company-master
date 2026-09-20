<?php

namespace App\Domain\Sales\Services;

use App\Domain\Customers\Services\CustomerAcquisitionService;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Game\Services\DemandCalculator;
use App\Models\Game;
use App\Models\InventoryBalance;

class SalesSimulator
{
    public function __construct(
        private readonly DemandCalculator $demandCalculator,
        private readonly LedgerService $ledger,
        private readonly SalesCapacityService $salesCapacity,
        private readonly CustomerAcquisitionService $customerAcquisition,
    ) {}

    /** @return array{sale_id: int, revenue_cents: int, cogs_cents: int, units_sold: int, stockout_product_ids: array<int, int>, commercial_capacity_units: int, unmet_demand_units: int, new_customers: int, customer_purchases: int} */
    public function simulate(Game $game, int $seedUsed, array $eventEffects = []): array
    {
        $company = $game->company;
        $sale = $company->sales()->create([
            'game_date' => $game->current_date,
            'status' => 'completed',
            'revenue_cents' => 0,
            'cogs_cents' => 0,
        ]);
        $revenueCents = 0;
        $cogsCents = 0;
        $unitsSold = 0;
        $stockoutProductIds = [];
        $unmetDemandUnits = 0;
        $newCustomers = 0;
        $customerPurchases = 0;
        $capacity = $this->salesCapacity->calculate($game, $seedUsed);
        $remainingCapacity = $capacity['total_units'];
        $channels = collect($capacity['channels'])->map(fn (array $channel) => [
            ...$channel,
            'remaining_units' => $channel['capacity_units'],
        ])->values()->all();
        $products = $company->products()->get()->sortBy(
            fn ($product) => $this->salesCapacity->productPriority(
                $seedUsed,
                $game->current_date->toDateString(),
                $product->sku,
            ),
        );

        foreach ($products as $product) {
            $balance = InventoryBalance::query()
                ->where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $demand = $this->demandCalculator->calculate(
                $product->base_daily_demand,
                intdiv(
                    ($product->reference_price_cents * ($eventEffects['products'][$product->id]['reference_price_factor'] ?? 10_000)) + 5_000,
                    10_000,
                ),
                $product->sale_price_cents,
                $seedUsed,
                $game->current_date->toDateString().':'.$product->sku,
                intdiv(
                    (($eventEffects['global_demand_factor'] ?? 10_000) * ($eventEffects['products'][$product->id]['demand_factor'] ?? 10_000)) + 5_000,
                    10_000,
                ),
            );
            $soldQuantity = min($demand, $balance->quantity, $remainingCapacity);
            $unmetDemandUnits += $demand - $soldQuantity;

            if ($demand > $balance->quantity) {
                $stockoutProductIds[] = $product->id;
            }

            if ($soldQuantity === 0) {
                continue;
            }

            $itemRevenueCents = $soldQuantity * $product->sale_price_cents;
            $itemCogsCents = $soldQuantity * $balance->average_cost_cents;

            $saleItem = $sale->items()->create([
                'product_id' => $product->id,
                'quantity' => $soldQuantity,
                'unit_price_cents' => $product->sale_price_cents,
                'unit_cost_cents' => $balance->average_cost_cents,
                'revenue_cents' => $itemRevenueCents,
                'cogs_cents' => $itemCogsCents,
            ]);
            $quantityToAttribute = $soldQuantity;
            foreach ($channels as $index => $channel) {
                if ($quantityToAttribute === 0) {
                    break;
                }

                $attributedQuantity = min($quantityToAttribute, $channel['remaining_units']);
                if ($attributedQuantity === 0) {
                    continue;
                }

                $saleItem->attributions()->create([
                    'employee_id' => $channel['employee_id'],
                    'quantity' => $attributedQuantity,
                    'revenue_cents' => $attributedQuantity * $product->sale_price_cents,
                ]);
                $channels[$index]['remaining_units'] -= $attributedQuantity;
                $quantityToAttribute -= $attributedQuantity;
            }
            $customerResult = $this->customerAcquisition->attribute($game, $saleItem, $soldQuantity, $seedUsed);
            $newCustomers += $customerResult['new_customers'];
            $customerPurchases += $customerResult['customer_purchases'];
            $sale->inventoryMovements()->create([
                'company_id' => $company->id,
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity' => -$soldQuantity,
                'unit_cost_cents' => $balance->average_cost_cents,
                'total_cost_cents' => $itemCogsCents,
                'game_date' => $game->current_date,
            ]);
            $balance->update(['quantity' => $balance->quantity - $soldQuantity]);

            $revenueCents += $itemRevenueCents;
            $cogsCents += $itemCogsCents;
            $unitsSold += $soldQuantity;
            $remainingCapacity -= $soldQuantity;
        }

        $sale->update([
            'revenue_cents' => $revenueCents,
            'cogs_cents' => $cogsCents,
        ]);

        if ($revenueCents > 0) {
            $financialEntry = $sale->financialEntry()->create([
                'company_id' => $company->id,
                'type' => 'inflow',
                'category' => 'sales_revenue',
                'description' => "Vendas de {$game->current_date->format('d/m/Y')}",
                'amount_cents' => $revenueCents,
                'game_date' => $game->current_date,
                'due_date' => $game->current_date,
            ]);
            $this->ledger->settle($financialEntry, $game->current_date);
        }

        return [
            'sale_id' => $sale->id,
            'revenue_cents' => $revenueCents,
            'cogs_cents' => $cogsCents,
            'units_sold' => $unitsSold,
            'stockout_product_ids' => $stockoutProductIds,
            'commercial_capacity_units' => $capacity['total_units'],
            'unmet_demand_units' => $unmetDemandUnits,
            'new_customers' => $newCustomers,
            'customer_purchases' => $customerPurchases,
        ];
    }
}
