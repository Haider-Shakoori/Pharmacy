<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id', 'medicine_id', 'description', 'ordered_quantity',
    'received_quantity', 'unit_cost', 'discount_amount',
    'landed_cost_allocated', 'line_total',
])]
class PurchaseOrderLine extends Model
{
    use HasUlids;

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'landed_cost_allocated' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }
}
