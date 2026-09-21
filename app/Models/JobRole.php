<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['department', 'name', 'salary_cents', 'sales_capacity_units', 'inventory_capacity_units', 'active'])]
class JobRole extends Model
{
    protected function casts(): array
    {
        return [
            'salary_cents' => 'integer',
            'sales_capacity_units' => 'integer',
            'inventory_capacity_units' => 'integer',
            'active' => 'boolean',
        ];
    }
}
