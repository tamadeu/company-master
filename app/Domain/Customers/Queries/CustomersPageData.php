<?php

namespace App\Domain\Customers\Queries;

use App\Models\CustomerPurchase;
use App\Models\Game;
use App\Models\PopulationNpc;

class CustomersPageData
{
    public function for(Game $game): array
    {
        $game->load(['company.customers.populationNpc', 'company.customers.purchases']);
        $company = $game->company;
        $customers = $company->customers;
        $populationCount = PopulationNpc::count();
        $customerNpcIds = $customers->pluck('population_npc_id');
        $purchases = CustomerPurchase::query()
            ->with(['customer.populationNpc', 'saleItem.product'])
            ->whereHas('customer', fn ($query) => $query->where('company_id', $company->id))
            ->latest('id')
            ->take(50)
            ->get();

        return [
            'game' => [
                'id' => $game->id,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
                'victoryDays' => config('game.victory.days'),
            ],
            'company' => ['id' => $company->id, 'name' => $company->name],
            'summary' => [
                'populationCount' => $populationCount,
                'customerCount' => $customers->count(),
                'conversionBasisPoints' => $populationCount > 0
                    ? intdiv($customers->count() * 10_000, $populationCount)
                    : 0,
                'purchaseCount' => (int) $customers->sum('purchase_count'),
                'lifetimeValueCents' => (int) $customers->sum('lifetime_value_cents'),
                'repeatCustomers' => $customers->where('purchase_count', '>', 1)->count(),
            ],
            'customers' => $customers->sortByDesc('lifetime_value_cents')->map(fn ($customer) => [
                'id' => $customer->id,
                'code' => $customer->populationNpc->code,
                'name' => $customer->populationNpc->name,
                'gender' => $customer->populationNpc->gender,
                'age' => abs((int) $customer->populationNpc->birth_date->diffInYears($game->current_date)),
                'city' => $customer->populationNpc->city,
                'state' => $customer->populationNpc->state,
                'email' => $customer->populationNpc->email,
                'acquiredOn' => $customer->acquired_on->toDateString(),
                'lastPurchaseOn' => $customer->last_purchase_on->toDateString(),
                'purchaseCount' => $customer->purchase_count,
                'lifetimeValueCents' => $customer->lifetime_value_cents,
                'averageTicketCents' => $customer->purchase_count > 0
                    ? intdiv($customer->lifetime_value_cents, $customer->purchase_count)
                    : 0,
            ])->values(),
            'prospects' => PopulationNpc::query()
                ->whereNotIn('id', $customerNpcIds)
                ->orderBy('code')
                ->take(20)
                ->get()
                ->map(fn ($npc) => [
                    'code' => $npc->code,
                    'name' => $npc->name,
                    'age' => abs((int) $npc->birth_date->diffInYears($game->current_date)),
                    'city' => $npc->city,
                    'state' => $npc->state,
                ]),
            'purchases' => $purchases->map(fn ($purchase) => [
                'id' => $purchase->id,
                'customerId' => $purchase->customer_id,
                'customerName' => $purchase->customer->populationNpc->name,
                'productName' => $purchase->saleItem->product->name,
                'quantity' => $purchase->quantity,
                'revenueCents' => $purchase->revenue_cents,
                'gameDate' => $purchase->game_date->toDateString(),
            ]),
        ];
    }
}
