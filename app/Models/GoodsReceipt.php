<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id', 'supplier_id', 'receipt_number', 'status', 'received_at',
    'idempotency_key', 'inventory_posted_at', 'created_by', 'notes',
])]
class GoodsReceipt extends Model
{
    use HasUlids;

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function lines(): HasMany { return $this->hasMany(GoodsReceiptLine::class); }

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'inventory_posted_at' => 'datetime'];
    }
}
