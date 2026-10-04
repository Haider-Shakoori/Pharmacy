<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'license_id',
    'key_hash',
    'key_hint',
    'platform',
    'issued_by_platform_admin_id',
    'consumed_activation_id',
    'generated_at',
    'consumed_at',
    'revoked_at',
])]
class LicenseActivationCode extends Model
{
    use CentralConnection, HasUlids;

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'issued_by_platform_admin_id');
    }

    public function consumedActivation(): BelongsTo
    {
        return $this->belongsTo(LicenseActivation::class, 'consumed_activation_id');
    }

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
