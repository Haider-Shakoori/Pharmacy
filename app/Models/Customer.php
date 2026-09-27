<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'credit_limit', 'is_active', 'notes'])]
class Customer extends Model
{
    use HasUlids;

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
