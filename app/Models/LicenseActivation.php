<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    'session_version',
    'current_user_id',
    'current_user_name',
    'current_user_email',
    'last_ip_address',
    'session_started_at',
    'session_last_seen_at',
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

    protected function casts(): array
    {
        return [
            'session_version' => 'integer',
            'session_started_at' => 'datetime',
            'session_last_seen_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
