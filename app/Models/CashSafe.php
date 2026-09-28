<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['stock_location_id', 'code', 'name', 'is_default', 'is_active', 'notes'])]
class CashSafe extends Model
{
    use HasUlids;

    protected $table = 'cash_safes';

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SafeMovement::class);
    }

    public function closings(): HasMany
    {
        return $this->hasMany(SafeClosing::class);
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
