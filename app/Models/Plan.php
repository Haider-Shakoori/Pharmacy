<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'description',
    'price',
    'currency',
    'billing_period',
    'max_users',
    'max_android_devices',
    'max_branches',
    'offline_grace_days',
    'features',
    'is_active',
    'sort_order',
])]
class Plan extends Model
{
    use HasFactory, HasUlids;

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_period' => BillingPeriod::class,
            'features' => 'array',
            'is_active' => 'boolean',
            'max_users' => 'integer',
            'max_android_devices' => 'integer',
            'max_branches' => 'integer',
            'offline_grace_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
