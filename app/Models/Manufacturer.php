<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'country', 'is_active'])]
class Manufacturer extends Model
{
    use BelongsToTenant, HasUlids;

    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
