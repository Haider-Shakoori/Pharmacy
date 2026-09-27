<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_return_id', 'method', 'amount', 'currency', 'reference'])]
class SaleReturnRefund extends Model
{
    use HasUlids;

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
