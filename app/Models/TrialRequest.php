<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'pharmacy_name',
    'requested_slug',
    'owner_name',
    'owner_email',
    'phone_whatsapp',
    'location',
    'preferred_locale',
    'notes',
    'owner_password_ciphertext',
    'status',
    'tenant_id',
    'reviewed_by',
    'reviewed_at',
    'decision_notes',
    'failure_reason',
])]
#[Hidden(['owner_password_ciphertext'])]
class TrialRequest extends Model
{
    use CentralConnection, HasUlids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
