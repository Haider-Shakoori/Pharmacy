<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['business_id', 'offer_tier', 'founder_number', 'first_year_amount',
    'renewal_amount', 'currency', 'confirmed_by', 'first_paid_at'])]
class CommercialEnrollment extends Model
{
    use CentralConnection, HasUlids;

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(CommercialPaymentReceipt::class, 'enrollment_id');
    }

    protected function casts(): array
    {
        return [
            'founder_number' => 'integer',
            'first_year_amount' => 'decimal:2',
            'renewal_amount' => 'decimal:2',
            'first_paid_at' => 'datetime',
        ];
    }
}
