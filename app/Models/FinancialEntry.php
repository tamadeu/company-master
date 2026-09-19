<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['company_id', 'type', 'category', 'description', 'amount_cents', 'game_date', 'due_date', 'paid_at', 'settled_game_date', 'recurring', 'reference_type', 'reference_id', 'metadata'])]
class FinancialEntry extends Model
{
    public const UPDATED_AT = null;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(FinancialSettlement::class);
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'game_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'immutable_datetime',
            'settled_game_date' => 'date',
            'recurring' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
