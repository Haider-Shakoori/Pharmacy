<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sale_number', 'stock_location_id', 'customer_id', 'prescription_reference',
    'prescriber_name', 'prescription_date', 'business_date',
    'status', 'currency', 'subtotal', 'discount_total', 'tax_total',
    'grand_total', 'paid_total', 'due_total', 'change_total', 'payment_status',
    'idempotency_key', 'notes', 'created_by', 'held_at', 'completed_at',
])]
class Sale extends Model
{
    use HasUlids;

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'prescription_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'paid_total' => 'decimal:4',
            'due_total' => 'decimal:4',
            'change_total' => 'decimal:4',
            'held_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
