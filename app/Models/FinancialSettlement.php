<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['company_id', 'financial_entry_id', 'type', 'amount_cents', 'game_date'])]
class FinancialSettlement extends Model
{
    public const UPDATED_AT = null;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Liquidações financeiras são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Liquidações financeiras são imutáveis.'));
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'game_date' => 'date',
        ];
    }
}
