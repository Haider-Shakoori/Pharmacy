<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'subscription_id',
    'key_hash',
    'key_hint',
    'windows_activation_key_hash',
    'windows_activation_key_hint',
    'windows_activation_key_version',
    'windows_activation_key_generated_at',
    'windows_activation_key_consumed_at',
    'windows_activation_id',
    'status',
    'version',
    'generated_at',
    'revoked_at',
])]
class License extends Model
{
    use CentralConnection, HasUlids;

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function supportActions(): HasMany
    {
        return $this->hasMany(LicenseSupportAction::class);
    }

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'version' => 'integer',
            'windows_activation_key_version' => 'integer',
            'generated_at' => 'datetime',
            'windows_activation_key_generated_at' => 'datetime',
            'windows_activation_key_consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
