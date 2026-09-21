<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['company_id', 'game_date', 'tick_key', 'status', 'revenue_cents', 'cogs_cents', 'commercial_capacity_units', 'unmet_demand_units', 'new_customers', 'customer_purchases', 'stockout_product_ids'])]
class Sale extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function financialEntry(): MorphOne
    {
        return $this->morphOne(FinancialEntry::class, 'reference');
    }

    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'revenue_cents' => 'integer',
            'cogs_cents' => 'integer',
            'commercial_capacity_units' => 'integer',
            'unmet_demand_units' => 'integer',
            'new_customers' => 'integer',
            'customer_purchases' => 'integer',
            'stockout_product_ids' => 'array',
        ];
    }
}
