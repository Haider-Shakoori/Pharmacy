<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'tenant_id',
    'activation_id',
    'device_id',
    'lease_token',
    'lease_public_key',
    'lease_issued_at',
    'lease_expires_at',
    'last_observed_at',
    'cloud_base_url',
])]
class LocalNodeState extends Model
{
    use CentralConnection;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'lease_issued_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'last_observed_at' => 'datetime',
        ];
    }
}