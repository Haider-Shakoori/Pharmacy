<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'contact_person', 'phone', 'whatsapp', 'email',
    'address', 'city', 'province', 'payment_terms_days', 'is_active', 'notes',
])]
class Supplier extends Model
{
    use HasUlids;

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
