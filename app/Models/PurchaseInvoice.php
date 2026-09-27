<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_id', 'purchase_order_id', 'goods_receipt_id', 'invoice_number',
    'supplier_invoice_number', 'invoice_date', 'due_date', 'currency', 'status',
    'subtotal', 'discount_total', 'landed_cost_total', 'grand_total',
    'paid_total', 'balance_due', 'created_by', 'notes',
])]
class PurchaseInvoice extends Model
{
    use HasUlids;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date', 'due_date' => 'date',
            'subtotal' => 'decimal:4', 'discount_total' => 'decimal:4',
            'landed_cost_total' => 'decimal:4', 'grand_total' => 'decimal:4',
            'paid_total' => 'decimal:4', 'balance_due' => 'decimal:4',
        ];
    }
}
