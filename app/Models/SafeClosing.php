<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cash_safe_id', 'business_date', 'status', 'opening_balance', 'cash_in',
    'cash_out', 'expected_balance', 'counted_balance', 'variance',
    'finalized_by', 'approved_by', 'reopened_by', 'finalized_at', 'approved_at',
    'reopened_at', 'closing_notes', 'reopen_reason',
])]
class SafeClosing extends Model
{
    use HasUlids;

    public function safe(): BelongsTo
    {
        return $this->belongsTo(CashSafe::class, 'cash_safe_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SafeClosingEvent::class)->latest('occurred_at');
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_balance' => 'decimal:4',
            'cash_in' => 'decimal:4',
            'cash_out' => 'decimal:4',
            'expected_balance' => 'decimal:4',
            'counted_balance' => 'decimal:4',
            'variance' => 'decimal:4',
            'finalized_at' => 'datetime',
            'approved_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
