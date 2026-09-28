<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'journal_number', 'business_date', 'occurred_at', 'status', 'currency',
    'source_type', 'source_id', 'source_event', 'source_number',
    'idempotency_key', 'reference', 'description', 'total_debit', 'total_credit',
    'posted_by', 'reversal_of_id', 'reversal_reason', 'posted_at',
])]
class JournalEntry extends Model
{
    use HasUlids;

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'occurred_at' => 'datetime',
            'total_debit' => 'decimal:4',
            'total_credit' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }
}
