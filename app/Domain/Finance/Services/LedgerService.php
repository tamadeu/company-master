<?php

namespace App\Domain\Finance\Services;

use App\Models\Company;
use App\Models\FinancialEntry;
use App\Models\FinancialSettlement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    public function settle(FinancialEntry $entry, CarbonInterface $gameDate): FinancialSettlement
    {
        return DB::transaction(function () use ($entry, $gameDate) {
            $lockedEntry = FinancialEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $existingSettlement = $lockedEntry->settlement()->first();

            if ($existingSettlement) {
                return $existingSettlement;
            }

            $company = Company::query()->lockForUpdate()->findOrFail($lockedEntry->company_id);
            $cashChangeCents = $lockedEntry->type === 'inflow'
                ? $lockedEntry->amount_cents
                : -$lockedEntry->amount_cents;

            if ($company->cash_balance_cents + $cashChangeCents < 0) {
                throw ValidationException::withMessages([
                    'cash' => 'Caixa insuficiente para liquidar esta obrigação.',
                ]);
            }

            $company->update([
                'cash_balance_cents' => $company->cash_balance_cents + $cashChangeCents,
            ]);
            $lockedEntry->update([
                'paid_at' => now(),
                'settled_game_date' => $gameDate,
            ]);

            return $lockedEntry->settlement()->create([
                'company_id' => $company->id,
                'type' => $lockedEntry->type,
                'amount_cents' => $lockedEntry->amount_cents,
                'game_date' => $gameDate,
            ]);
        });
    }
}
