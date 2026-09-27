<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sale_id', 'medicine_id', 'description', 'sale_unit', 'quantity',
    'unit_price', 'discount_amount', 'tax_amount', 'line_total',
    'cost_total', 'prescription_required',
])]
class SaleLine extends Model
{
    use HasUlids;

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SaleBatchAllocation::class);
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'cost_total' => 'decimal:4',
            'prescription_required' => 'boolean',
        ];
    }
}
