<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['safe_closing_id', 'event_type', 'actor_id', 'reason', 'snapshot', 'occurred_at'])]
class SafeClosingEvent extends Model
{
    use HasUlids;

    public function closing(): BelongsTo
    {
        return $this->belongsTo(SafeClosing::class, 'safe_closing_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'occurred_at' => 'datetime'];
    }
}
