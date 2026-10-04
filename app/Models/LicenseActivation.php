<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'license_id',
    'device_id',
    'device_name',
    'platform',
    'app_version',
    'device_model',
    'os_version',
    'build_number',
    'activated_at',
    'last_seen_at',
    'revoked_at',
])]
class LicenseActivation extends Model
{
    use CentralConnection, HasUlids;

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function desktopSessions(): HasMany
    {
        return $this->hasMany(DesktopUserSession::class, 'license_activation_id');
    }

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
