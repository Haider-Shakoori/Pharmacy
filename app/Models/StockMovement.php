<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_batch_id', 'medicine_id', 'branch_id', 'stock_location_id',
    'movement_type', 'quantity_delta', 'balance_after', 'unit_cost',
    'source_type', 'source_id', 'source_line_id', 'reason',
    'idempotency_key', 'actor_id', 'occurred_at', 'metadata',
])]
class StockMovement extends Model
{
    use HasUlids;

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
