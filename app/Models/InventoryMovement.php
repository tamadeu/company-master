<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable(['company_id', 'product_id', 'type', 'quantity', 'unit_cost_cents', 'total_cost_cents', 'game_date'])]
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Movimentações de estoque são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Movimentações de estoque são imutáveis.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost_cents' => 'integer',
            'total_cost_cents' => 'integer',
            'game_date' => 'date',
        ];
    }
}
