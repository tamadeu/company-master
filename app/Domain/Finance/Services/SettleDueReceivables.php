<?php

namespace App\Domain\Finance\Services;

use App\Models\Company;
use Carbon\CarbonInterface;

class SettleDueReceivables
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @return array{amount_cents: int, entry_ids: array<int, int>} */
    public function settle(Company $company, CarbonInterface $gameDate): array
    {
        $entries = $company->financialEntries()
            ->where('type', 'inflow')
            ->whereNull('paid_at')
            ->whereDate('due_date', '<=', $gameDate)
            ->lockForUpdate()
            ->get();

        foreach ($entries as $entry) {
            $this->ledger->settle($entry, $gameDate);
        }

        return [
            'amount_cents' => (int) $entries->sum('amount_cents'),
            'entry_ids' => $entries->modelKeys(),
        ];
    }
}
