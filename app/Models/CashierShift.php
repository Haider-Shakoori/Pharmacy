<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_location_id', 'user_id', 'business_date', 'status',
    'opening_cash', 'expected_cash', 'counted_cash', 'variance',
    'opened_at', 'closed_at', 'closing_notes',
])]
class CashierShift extends Model
{
    use HasUlids;

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_cash' => 'decimal:4',
            'expected_cash' => 'decimal:4',
            'counted_cash' => 'decimal:4',
            'variance' => 'decimal:4',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
