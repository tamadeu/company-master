<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sale_id', 'product_id', 'quantity', 'unit_price_cents', 'unit_cost_cents', 'revenue_cents', 'cogs_cents'])]
class SaleItem extends Model
{
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(SaleAttribution::class);
    }
}
