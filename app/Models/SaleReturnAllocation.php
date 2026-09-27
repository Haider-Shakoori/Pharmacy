<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_return_line_id', 'sale_batch_allocation_id', 'product_batch_id',
    'stock_movement_id', 'quantity', 'restocked',
])]
class SaleReturnAllocation extends Model
{
    use HasUlids;

    public function originalAllocation(): BelongsTo
    {
        return $this->belongsTo(SaleBatchAllocation::class, 'sale_batch_allocation_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'restocked' => 'boolean',
        ];
    }
}
