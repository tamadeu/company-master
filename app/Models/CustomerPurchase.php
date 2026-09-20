<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_item_id', 'customer_id', 'quantity', 'revenue_cents', 'game_date'])]
class CustomerPurchase extends Model
{
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'revenue_cents' => 'integer',
            'game_date' => 'date',
        ];
    }
}
