<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'slug',
    'status',
    'timezone',
    'currency',
    'locale',
    'settings',
])]
class Tenant extends Model
{
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => 'active',
        'timezone' => 'Asia/Kabul',
        'currency' => 'AFN',
        'locale' => 'en',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class)
            ->withoutGlobalScope(TenantScope::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
        ];
    }
}
