<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'population_npc_id', 'acquired_on', 'last_purchase_on', 'purchase_count', 'lifetime_value_cents', 'status'])]
class Customer extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function populationNpc(): BelongsTo
    {
        return $this->belongsTo(PopulationNpc::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CustomerPurchase::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'last_purchase_on' => 'date',
            'purchase_count' => 'integer',
            'lifetime_value_cents' => 'integer',
        ];
    }
}
