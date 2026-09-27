<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id', 'payment_number', 'method', 'amount', 'currency',
    'reference', 'paid_at', 'created_by',
])]
class SalePayment extends Model
{
    use HasUlids;

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }
}
