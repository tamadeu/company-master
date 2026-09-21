<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sku', 'name', 'reference_price_cents', 'base_daily_demand', 'base_cost_cents', 'active'])]
class ProductTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'reference_price_cents' => 'integer',
            'base_daily_demand' => 'integer',
            'base_cost_cents' => 'integer',
            'active' => 'boolean',
        ];
    }
}
