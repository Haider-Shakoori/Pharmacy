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

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'version' => 'integer',
            'generated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
