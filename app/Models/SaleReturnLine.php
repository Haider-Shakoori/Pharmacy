<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sale_return_id', 'sale_line_id', 'medicine_id', 'quantity', 'refund_amount', 'disposition'])]
class SaleReturnLine extends Model
{
    use HasUlids;

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SaleReturnAllocation::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'refund_amount' => 'decimal:4',
        ];
    }
}
