<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'license_id',
    'activation_id',
    'platform_admin_id',
    'action',
    'reason',
    'metadata',
    'created_at',
])]
class LicenseSupportAction extends Model
{
    use CentralConnection, HasUlids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
