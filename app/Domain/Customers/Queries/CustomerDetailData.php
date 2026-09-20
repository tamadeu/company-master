<?php

namespace App\Domain\Customers\Queries;

use App\Models\Customer;
use App\Models\Game;

class CustomerDetailData
{
    public function for(Game $game, Customer $customer): array
    {
        $customer->load(['populationNpc', 'purchases.saleItem.product']);
        $purchases = $customer->purchases->sortByDesc('game_date');
        $totalUnits = (int) $purchases->sum('quantity');
        $activeDays = abs((int) $customer->acquired_on->diffInDays($game->current_date)) + 1;
        $daysSinceLastPurchase = abs((int) $customer->last_purchase_on->diffInDays($game->current_date));
        $companyCustomers = $game->company->customers()->orderByDesc('lifetime_value_cents')->pluck('id');
        $rankingPosition = $companyCustomers->search($customer->id);
        $products = $purchases
            ->groupBy(fn ($purchase) => $purchase->saleItem->product_id)
            ->map(fn ($items) => [
                'productId' => $items->first()->saleItem->product_id,
                'productName' => $items->first()->saleItem->product->name,
                'purchaseCount' => $items->count(),
                'units' => (int) $items->sum('quantity'),
                'revenueCents' => (int) $items->sum('revenue_cents'),
                'revenueShareBasisPoints' => $customer->lifetime_value_cents > 0
                    ? intdiv($items->sum('revenue_cents') * 10_000, $customer->lifetime_value_cents)
                    : 0,
            ])->sortByDesc('revenueCents')->values();
        $timeline = $purchases
            ->groupBy(fn ($purchase) => $purchase->game_date->toDateString())
            ->map(fn ($items, $date) => [
                'date' => $date,
                'purchaseCount' => $items->count(),
                'units' => (int) $items->sum('quantity'),
                'revenueCents' => (int) $items->sum('revenue_cents'),
            ])->sortBy('date')->values();

        return [
            'game' => [
                'id' => $game->id,
                'currentDate' => $game->current_date->toDateString(),
                'dayNumber' => abs((int) $game->current_date->diffInDays(config('game.initial_date'))) + 1,
                'victoryDays' => config('game.victory.days'),
            ],
            'company' => ['id' => $game->company->id, 'name' => $game->company->name],
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->populationNpc->code,
                'name' => $customer->populationNpc->name,
                'gender' => $customer->populationNpc->gender,
                'age' => abs((int) $customer->populationNpc->birth_date->diffInYears($game->current_date)),
                'birthDate' => $customer->populationNpc->birth_date->toDateString(),
                'city' => $customer->populationNpc->city,
                'state' => $customer->populationNpc->state,
                'email' => $customer->populationNpc->email,
                'acquiredOn' => $customer->acquired_on->toDateString(),
                'lastPurchaseOn' => $customer->last_purchase_on->toDateString(),
                'status' => $customer->status,
            ],
            'analytics' => [
                'purchaseCount' => $customer->purchase_count,
                'totalUnits' => $totalUnits,
                'lifetimeValueCents' => $customer->lifetime_value_cents,
                'averageTicketCents' => $customer->purchase_count > 0
                    ? intdiv($customer->lifetime_value_cents, $customer->purchase_count)
                    : 0,
                'daysSinceLastPurchase' => $daysSinceLastPurchase,
                'activeDays' => $activeDays,
                'purchasesPer30DaysBasisPoints' => intdiv($customer->purchase_count * 30 * 10_000, $activeDays),
                'rankingPosition' => $rankingPosition === false ? null : $rankingPosition + 1,
                'segment' => $this->segment($customer),
            ],
            'products' => $products,
            'timeline' => $timeline,
            'purchases' => $purchases->map(fn ($purchase) => [
                'id' => $purchase->id,
                'date' => $purchase->game_date->toDateString(),
                'productName' => $purchase->saleItem->product->name,
                'quantity' => $purchase->quantity,
                'revenueCents' => $purchase->revenue_cents,
                'unitPriceCents' => intdiv($purchase->revenue_cents, $purchase->quantity),
            ])->values(),
        ];
    }

    private function segment(Customer $customer): string
    {
        if ($customer->purchase_count >= 5 || $customer->lifetime_value_cents >= 50_000) {
            return 'vip';
        }

        if ($customer->purchase_count >= 2) {
            return 'recurring';
        }

        return 'new';
    }
}
