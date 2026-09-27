<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'goods_receipt_id', 'purchase_order_line_id', 'medicine_id',
    'received_quantity', 'bonus_quantity', 'batch_number',
    'manufactured_at', 'expires_at', 'unit_cost', 'sale_price',
])]
class GoodsReceiptLine extends Model
{
    use HasUlids;

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:4',
            'bonus_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'manufactured_at' => 'date',
            'expires_at' => 'date',
        ];
    }
}
