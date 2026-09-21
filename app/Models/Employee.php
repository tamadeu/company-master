<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['company_id', 'population_npc_id', 'name', 'department', 'role', 'monthly_salary_cents', 'hired_on', 'terminated_on', 'status'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function populationNpc(): BelongsTo
    {
        return $this->belongsTo(PopulationNpc::class);
    }

    public function salaryEntries(): MorphMany
    {
        return $this->morphMany(FinancialEntry::class, 'reference');
    }

    public function saleAttributions(): HasMany
    {
        return $this->hasMany(SaleAttribution::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    protected function casts(): array
    {
        return [
            'monthly_salary_cents' => 'integer',
            'hired_on' => 'date',
            'terminated_on' => 'date',
        ];
    }
}
