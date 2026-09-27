<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_id', 'number', 'status', 'order_date', 'expected_date', 'currency',
    'subtotal', 'discount_total', 'landed_cost_total', 'grand_total', 'notes',
    'created_by', 'approved_by', 'submitted_at', 'approved_at', 'cancelled_at',
])]
class PurchaseOrder extends Model
{
    use HasUlids;

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function lines(): HasMany { return $this->hasMany(PurchaseOrderLine::class); }
    public function receipts(): HasMany { return $this->hasMany(GoodsReceipt::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'landed_cost_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
