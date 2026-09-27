<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'stock_location_id', 'business_date', 'status', 'gross_sales',
    'discount_total', 'returns_total', 'cash_collected', 'bank_collected',
    'mobile_collected', 'credit_sales', 'opening_cash', 'expected_cash',
    'counted_cash', 'variance', 'finalized_by', 'approved_by', 'reopened_by',
    'finalized_at', 'approved_at', 'reopened_at', 'closing_notes', 'reopen_reason',
])]
class DailyClosing extends Model
{
    use HasUlids;

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DailyClosingEvent::class);
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'gross_sales' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'returns_total' => 'decimal:4',
            'cash_collected' => 'decimal:4',
            'bank_collected' => 'decimal:4',
            'mobile_collected' => 'decimal:4',
            'credit_sales' => 'decimal:4',
            'opening_cash' => 'decimal:4',
            'expected_cash' => 'decimal:4',
            'counted_cash' => 'decimal:4',
            'variance' => 'decimal:4',
            'finalized_at' => 'datetime',
            'approved_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
