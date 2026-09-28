<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cash_safe_id', 'business_date', 'direction', 'movement_type', 'amount',
    'source_type', 'source_id', 'reference', 'reason', 'idempotency_key',
    'created_by', 'occurred_at',
])]
class SafeMovement extends Model
{
    use HasUlids;

    public function safe(): BelongsTo
    {
        return $this->belongsTo(CashSafe::class, 'cash_safe_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }
}
