<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $attributes = [
        'status' => 'active',
        'provisioning_status' => 'pending',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'status',
            'provisioning_status',
            'provisioning_error',
        ];
    }

    public function business(): HasOne
    {
        return $this->hasOne(Business::class);
    }

    public function provisioningEvents(): HasMany
    {
        return $this->hasMany(ProvisioningEvent::class)->latest('occurred_at');
    }

    public function subscription(): HasOneThrough
    {
        return $this->hasOneThrough(
            Subscription::class,
            Business::class,
            'tenant_id',
            'business_id',
            'id',
            'id',
        );
    }

    public function getNameAttribute(): ?string
    {
        return $this->business?->pharmacy_name;
    }

    public function getSlugAttribute(): ?string
    {
        return $this->business?->slug;
    }

    public function getTimezoneAttribute(): string
    {
        return $this->business?->default_timezone ?? 'Asia/Kabul';
    }

    public function getCurrencyAttribute(): string
    {
        return $this->business?->billing_currency ?? 'AFN';
    }

    public function getLocaleAttribute(): string
    {
        return $this->business?->default_locale ?? 'en';
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }
}
