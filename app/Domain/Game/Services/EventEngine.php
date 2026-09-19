<?php

namespace App\Domain\Game\Services;

use App\Domain\Finance\Services\LedgerService;
use App\Models\Game;
use App\Models\GameEvent;

class EventEngine
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @return array{global_demand_factor: int, products: array<int, array{demand_factor: int, reference_price_factor: int}>} */
    public function activeEffects(Game $game): array
    {
        $this->expirePastEvents($game);
        $effects = ['global_demand_factor' => 10_000, 'products' => []];
        $events = $game->events()
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $game->current_date)
            ->whereDate('ends_on', '>=', $game->current_date)
            ->get();

        foreach ($events as $event) {
            if ($event->type === 'heavy_rain') {
                $effects['global_demand_factor'] = $this->combineFactors(
                    $effects['global_demand_factor'],
                    $event->payload['demand_factor_basis_points'],
                );
            }

            if (in_array($event->type, ['influencer_recommendation', 'trending_product'], true)) {
                $productId = $event->payload['product_id'];
                $current = $effects['products'][$productId] ?? [
                    'demand_factor' => 10_000,
                    'reference_price_factor' => 10_000,
                ];
                $effects['products'][$productId] = [
                    'demand_factor' => $this->combineFactors(
                        $current['demand_factor'],
                        $event->payload['demand_factor_basis_points'],
                    ),
                    'reference_price_factor' => $this->combineFactors(
                        $current['reference_price_factor'],
                        $event->payload['reference_price_factor_basis_points'] ?? 10_000,
                    ),
                ];
            }
        }

        return $effects;
    }

    public function trigger(Game $game, int $seed): ?GameEvent
    {
        $existingEvent = $game->events()->whereDate('triggered_on', $game->current_date)->first();
        if ($existingEvent) {
            return $existingEvent;
        }

        $chance = config('game.events.daily_chance_basis_points');
        if ($this->number($seed, 'event-roll', 1, 10_000) > $chance) {
            return null;
        }

        $definitions = config('game.events.definitions');
        $totalWeight = array_sum(array_column($definitions, 'weight'));
        $choice = $this->number($seed, 'event-choice', 1, $totalWeight);
        $type = array_key_first($definitions);

        foreach ($definitions as $candidateType => $definition) {
            $choice -= $definition['weight'];
            if ($choice <= 0) {
                $type = $candidateType;
                break;
            }
        }

        return $this->createEvent($game, $type, $definitions[$type], $seed);
    }

    private function createEvent(Game $game, string $type, array $definition, int $seed): ?GameEvent
    {
        return match ($type) {
            'influencer_recommendation' => $this->productDemandEvent(
                $game, $type, $definition, $seed,
                'Influenciador recomenda um produto',
                'A recomendação elevará temporariamente a demanda.',
            ),
            'heavy_rain' => $this->timedEvent(
                $game, $type, $definition,
                'Chuva intensa',
                'A demanda geral ficará reduzida temporariamente.',
                ['demand_factor_basis_points' => $definition['demand_factor_basis_points']],
            ),
            'supplier_delay' => $this->delaySupplier($game, $definition),
            'trending_product' => $this->productDemandEvent(
                $game, $type, $definition, $seed,
                'Produto em alta',
                'Demanda e preço de referência ficarão maiores temporariamente.',
            ),
            'emergency_maintenance' => $this->maintenance($game, $definition, $seed),
            default => null,
        };
    }

    private function productDemandEvent(Game $game, string $type, array $definition, int $seed, string $title, string $description): ?GameEvent
    {
        $products = $game->company->products()->orderBy('id')->get();
        if ($products->isEmpty()) {
            return null;
        }

        $product = $products[$this->number($seed, "{$type}-product", 0, $products->count() - 1)];

        return $this->timedEvent($game, $type, $definition, $title, $description, [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'demand_factor_basis_points' => $definition['demand_factor_basis_points'],
            'reference_price_factor_basis_points' => $definition['reference_price_factor_basis_points'] ?? 10_000,
        ]);
    }

    private function timedEvent(Game $game, string $type, array $definition, string $title, string $description, array $payload): GameEvent
    {
        $startsOn = $game->current_date->copy()->addDay();

        return $game->events()->create([
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'triggered_on' => $game->current_date,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays($definition['duration_days'] - 1),
            'status' => 'active',
        ]);
    }

    private function delaySupplier(Game $game, array $definition): ?GameEvent
    {
        $order = $game->company->purchaseOrders()->where('status', 'ordered')->orderBy('expected_delivery_date')->first();
        if (! $order) {
            return null;
        }

        $order->update([
            'expected_delivery_date' => $order->expected_delivery_date->addDays($definition['delay_days']),
        ]);

        return $game->events()->create([
            'type' => 'supplier_delay',
            'title' => 'Fornecedor atrasado',
            'description' => "O pedido #{$order->id} foi adiado.",
            'payload' => ['purchase_order_id' => $order->id, 'delay_days' => $definition['delay_days']],
            'triggered_on' => $game->current_date,
            'starts_on' => $game->current_date,
            'ends_on' => $game->current_date,
            'status' => 'active',
        ]);
    }

    private function maintenance(Game $game, array $definition, int $seed): GameEvent
    {
        $amountCents = $this->number(
            $seed,
            'maintenance-amount',
            $definition['minimum_cents'],
            $definition['maximum_cents'],
        );
        $entry = $game->company->financialEntries()->create([
            'type' => 'outflow',
            'category' => 'maintenance',
            'description' => 'Manutenção emergencial',
            'amount_cents' => $amountCents,
            'game_date' => $game->current_date,
            'due_date' => $game->current_date,
        ]);

        $settled = $game->company->cash_balance_cents >= $amountCents;
        if ($settled) {
            $this->ledger->settle($entry, $game->current_date);
        }

        return $game->events()->create([
            'type' => 'emergency_maintenance',
            'title' => 'Manutenção emergencial',
            'description' => 'Uma despesa inesperada afetou a operação.',
            'payload' => ['amount_cents' => $amountCents, 'financial_entry_id' => $entry->id, 'settled' => $settled],
            'triggered_on' => $game->current_date,
            'starts_on' => $game->current_date,
            'ends_on' => $game->current_date,
            'status' => 'active',
        ]);
    }

    private function expirePastEvents(Game $game): void
    {
        $game->events()
            ->where('status', 'active')
            ->whereDate('ends_on', '<', $game->current_date)
            ->update(['status' => 'expired']);
    }

    private function combineFactors(int $left, int $right): int
    {
        return intdiv(($left * $right) + 5_000, 10_000);
    }

    private function number(int $seed, string $context, int $minimum, int $maximum): int
    {
        $value = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return $minimum + ($value % (($maximum - $minimum) + 1));
    }
}
