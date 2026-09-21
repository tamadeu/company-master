<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['company_id', 'supplier_id', 'employee_id', 'status', 'automatic', 'ordered_at_game_date', 'expected_delivery_date', 'received_at_game_date', 'total_cents'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
            'ordered_at_game_date' => 'date',
            'expected_delivery_date' => 'date',
            'received_at_game_date' => 'date',
            'total_cents' => 'integer',
            'automatic' => 'boolean',
        ];
    }
}
