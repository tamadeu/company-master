<?php

namespace App\Domain\Game\Actions;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Game\Services\GameAutomationClock;
use App\Domain\Game\Services\OfficeLocationCatalog;
use App\Models\Game;
use App\Models\ProductTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateGame
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly GameAutomationClock $automationClock,
        private readonly OfficeLocationCatalog $officeLocations,
    ) {}

    public function execute(User $user, string $gameName, string $companyName, ?int $seed = null, string $officeLocation = 'downtown'): Game
    {
        return DB::transaction(function () use ($user, $gameName, $companyName, $seed, $officeLocation) {
            $gameDate = CarbonImmutable::parse(config('game.initial_date'));
            $location = $this->officeLocations->find($officeLocation);
            $game = $user->games()->create([
                'name' => $gameName,
                'status' => 'active',
                'seed' => $seed ?? random_int(1, PHP_INT_MAX),
                'current_date' => $gameDate,
                'started_at' => now(),
                'automation_enabled' => app()->environment('production') && config('game.automation.enabled'),
                'next_processing_at' => app()->environment('production') && config('game.automation.enabled')
                    ? $this->automationClock->nextProcessingAt()
                    : null,
            ]);

            $company = $game->company()->create([
                'name' => $companyName,
                'cash_balance_cents' => 0,
                'settings' => [
                    'office_location' => $location['key'],
                    'office_location_name' => $location['name'],
                    'office_rent_cents' => $location['rent_cents'],
                    'office_demand_factor_basis_points' => $location['demand_factor_basis_points'],
                ],
            ]);

            $capitalEntry = $company->financialEntries()->create([
                'type' => 'inflow',
                'category' => 'initial_capital',
                'description' => 'Capital inicial',
                'amount_cents' => config('game.initial_capital_cents'),
                'game_date' => $gameDate,
            ]);
            $this->ledger->settle($capitalEntry, $gameDate);
            $company->refresh();

            $products = ProductTemplate::query()
                ->where('active', true)
                ->orderBy('id')
                ->get()
                ->mapWithKeys(function (ProductTemplate $definition) use ($company) {
                    $product = $company->products()->create([
                        'sku' => $definition->sku,
                        'name' => $definition->name,
                        'sale_price_cents' => $definition->reference_price_cents,
                        'reference_price_cents' => $definition->reference_price_cents,
                        'base_daily_demand' => $definition->base_daily_demand,
                    ]);

                    $company->inventoryBalances()->create([
                        'product_id' => $product->id,
                        'quantity' => 0,
                        'average_cost_cents' => 0,
                    ]);

                    return [$definition->sku => ['model' => $product, 'base_cost_cents' => $definition->base_cost_cents]];
                });

            foreach (config('game.suppliers') as $definition) {
                $supplier = $company->suppliers()->create([
                    'name' => $definition['name'],
                    'profile' => $definition['profile'],
                    'lead_time_days' => $definition['lead_time_days'],
                    'payment_term_days' => $definition['payment_term_days'],
                    'reliability_percent' => $definition['reliability_percent'],
                ]);

                foreach ($products as $product) {
                    $supplier->products()->create([
                        'product_id' => $product['model']->id,
                        'cost_cents' => intdiv(($product['base_cost_cents'] * $definition['cost_percent']) + 50, 100),
                        'minimum_quantity' => 1,
                    ]);
                }
            }

            foreach (config('game.fixed_expenses') as $expense) {
                $dueDate = $gameDate->setDay($expense['day_of_month']);
                $amountCents = $expense['description'] === 'Aluguel'
                    ? $location['rent_cents']
                    : $expense['amount_cents'];
                $company->financialEntries()->create([
                    'type' => 'outflow',
                    'category' => 'fixed_expense',
                    'description' => $expense['description'],
                    'amount_cents' => $amountCents,
                    'game_date' => $dueDate,
                    'due_date' => $dueDate,
                    'recurring' => true,
                    'metadata' => [
                        'day_of_month' => $expense['day_of_month'],
                        ...($expense['description'] === 'Aluguel' ? ['office_location' => $location['key']] : []),
                    ],
                ]);
            }

            return $game->load('company.products', 'company.suppliers.products', 'company.inventoryBalances', 'company.financialEntries');
        });
    }
}
