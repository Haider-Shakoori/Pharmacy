<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'plan_id',
    'status',
    'trial_started_at',
    'trial_ends_at',
    'starts_at',
    'ends_at',
    'auto_renew',
    'notes',
])]
class Subscription extends Model
{
    use HasFactory, HasUlids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function license(): HasOne
    {
        return $this->hasOne(License::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }
}
