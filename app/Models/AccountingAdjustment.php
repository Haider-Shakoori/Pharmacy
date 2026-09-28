<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'adjustment_number', 'business_date', 'currency', 'debit_account_id',
    'credit_account_id', 'stock_location_id', 'amount', 'reference', 'reason',
    'status', 'idempotency_key', 'created_by', 'posted_at', 'reversed_at',
])]
class AccountingAdjustment extends Model
{
    use HasUlids;

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'credit_account_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount' => 'decimal:4',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
