<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'medicine_id', 'supplier_id', 'purchase_order_id', 'goods_receipt_id',
    'branch_id', 'stock_location_id', 'batch_number', 'batch_key',
    'manufactured_at', 'expires_at', 'status', 'received_quantity',
    'available_quantity', 'purchase_cost', 'sale_price', 'last_movement_at',
])]
class ProductBatch extends Model
{
    use HasUlids;

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(BatchStatusEvent::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isBefore(today()) ?? false;
    }

    public function isSellable(): bool
    {
        return $this->status === 'active'
            && ! $this->isExpired()
            && BigDecimal::of($this->available_quantity)->isGreaterThan(BigDecimal::zero());
    }

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'received_quantity' => 'decimal:4',
            'available_quantity' => 'decimal:4',
            'purchase_cost' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'last_movement_at' => 'datetime',
        ];
    }
}
