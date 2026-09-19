<?php

namespace App\Domain\Finance\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\FinancialEntry;
use Carbon\CarbonInterface;

class SettleDuePayables
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @return array{amount_cents: int, entry_ids: array<int, int>, insufficient: bool} */
    public function settle(Company $company, CarbonInterface $gameDate): array
    {
        $entries = $company->financialEntries()
            ->where('type', 'outflow')
            ->whereNull('paid_at')
            ->whereDate('due_date', '<=', $gameDate)
            ->lockForUpdate()
            ->get();
        $totalCents = (int) $entries->sum('amount_cents');

        if ($totalCents > $company->cash_balance_cents) {
            return ['amount_cents' => 0, 'entry_ids' => [], 'insufficient' => true];
        }

        foreach ($entries as $entry) {
            $this->ledger->settle($entry, $gameDate);
            $this->scheduleNextOccurrence($entry);
        }

        return [
            'amount_cents' => $totalCents,
            'entry_ids' => $entries->modelKeys(),
            'insufficient' => false,
        ];
    }

    private function scheduleNextOccurrence(FinancialEntry $entry): void
    {
        if (! $entry->recurring || ! $entry->due_date) {
            return;
        }

        $nextDueDate = $entry->due_date->addMonthNoOverflow();
        if ($entry->reference instanceof Employee) {
            if ($entry->reference->status !== 'active') {
                return;
            }

            $entry->reference->salaryEntries()->firstOrCreate(
                [
                    'category' => $entry->category,
                    'description' => $entry->description,
                    'due_date' => $nextDueDate,
                ],
                [
                    'company_id' => $entry->company_id,
                    'type' => $entry->type,
                    'amount_cents' => $entry->amount_cents,
                    'game_date' => $nextDueDate,
                    'recurring' => true,
                    'metadata' => $entry->metadata,
                ],
            );

            return;
        }

        $entry->company->financialEntries()->firstOrCreate(
            [
                'category' => $entry->category,
                'description' => $entry->description,
                'due_date' => $nextDueDate,
            ],
            [
                'type' => $entry->type,
                'amount_cents' => $entry->amount_cents,
                'game_date' => $nextDueDate,
                'recurring' => true,
                'metadata' => $entry->metadata,
            ],
        );
    }
}
