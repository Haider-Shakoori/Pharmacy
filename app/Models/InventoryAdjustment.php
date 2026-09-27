<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'stock_location_id', 'reason_code', 'status', 'notes',
    'created_by', 'posted_by', 'posted_at',
])]
class InventoryAdjustment extends Model
{
    use HasUlids;

    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function lines(): HasMany { return $this->hasMany(InventoryAdjustmentLine::class); }

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }
}
