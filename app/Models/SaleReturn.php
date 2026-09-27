<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'return_number', 'sale_id', 'stock_location_id', 'business_date',
    'status', 'refund_total', 'idempotency_key', 'reason', 'created_by', 'completed_at',
])]
class SaleReturn extends Model
{
    use HasUlids;

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleReturnRefund::class);
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'refund_total' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }
}
