<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([
    'source', 'source_id', 'kind', 'language', 'contact_name', 'phone',
    'email', 'pharmacy', 'message', 'details', 'status', 'is_test',
    'internal_notes', 'follow_up_at', 'reviewed_by', 'submitted_at',
])]
class WebsiteLead extends Model
{
    use CentralConnection, HasUlids;

    public const STATUSES = [
        'new', 'contacted', 'demo_scheduled', 'trial',
        'converted', 'lost', 'completed',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'details' => 'array',
            'is_test' => 'boolean',
            'submitted_at' => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }
}
