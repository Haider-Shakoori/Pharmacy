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
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
