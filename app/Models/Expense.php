<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'expense_number', 'expense_account_id', 'payment_account_id', 'stock_location_id',
    'business_date', 'currency', 'amount', 'payee', 'reference', 'notes', 'status',
    'idempotency_key', 'created_by', 'posted_at', 'reversed_at',
])]
class Expense extends Model
{
    use HasUlids;

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'expense_account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'payment_account_id');
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
