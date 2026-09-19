<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['game_id', 'game_date', 'sales_revenue_cents', 'units_sold', 'cogs_cents', 'expenses_cents', 'cash_change_cents', 'inventory_value_cents', 'summary'])]
class DailySnapshot extends Model
{
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Snapshots diários são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Snapshots diários são imutáveis.'));
    }

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'sales_revenue_cents' => 'integer',
            'units_sold' => 'integer',
            'cogs_cents' => 'integer',
            'expenses_cents' => 'integer',
            'cash_change_cents' => 'integer',
            'inventory_value_cents' => 'integer',
            'summary' => 'array',
        ];
    }
}
