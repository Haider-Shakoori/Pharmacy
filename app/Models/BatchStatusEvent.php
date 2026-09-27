<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_batch_id', 'from_status', 'to_status', 'reason', 'actor_id', 'changed_at'])]
class BatchStatusEvent extends Model
{
    use HasUlids;

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
