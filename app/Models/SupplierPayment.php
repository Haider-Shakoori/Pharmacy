<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_invoice_id', 'supplier_id', 'payment_number', 'amount', 'currency',
    'method', 'reference', 'paid_at', 'idempotency_key', 'created_by', 'notes',
])]
class SupplierPayment extends Model
{
    use HasUlids;

    public function invoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'paid_at' => 'datetime'];
    }
}
