<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'tenant_id',
    'pharmacy_name',
    'slug',
    'contact_person',
    'phone_whatsapp',
    'location',
    'owner_email',
    'billing_currency',
    'default_timezone',
    'default_locale',
    'trial_used_at',
])]
class Business extends Model
{
    use CentralConnection, HasUlids;

    protected function casts(): array
    {
        return [
            'trial_used_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
