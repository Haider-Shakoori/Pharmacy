<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class LicenseSupportAction extends Model
{
    use CentralConnection, HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'license_id',
        'activation_id',
        'platform_admin_id',
        'action',
        'reason',
        'metadata',
        'created_at',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(LicenseActivation::class);
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
