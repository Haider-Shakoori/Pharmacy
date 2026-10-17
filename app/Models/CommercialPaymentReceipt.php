<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['enrollment_id', 'kind', 'amount', 'currency',
    'payment_reference', 'paid_at', 'coverage_ends_at', 'confirmed_by'])]
class CommercialPaymentReceipt extends Model
{
    use CentralConnection, HasUlids;

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CommercialEnrollment::class, 'enrollment_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'coverage_ends_at' => 'datetime',
        ];
    }
}
