<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['daily_closing_id', 'event_type', 'actor_id', 'reason', 'snapshot', 'occurred_at'])]
class DailyClosingEvent extends Model
{
    use HasUlids;

    public function closing(): BelongsTo
    {
        return $this->belongsTo(DailyClosing::class, 'daily_closing_id');
    }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
