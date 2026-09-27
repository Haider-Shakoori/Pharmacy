<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'medicine_category_id',
    'manufacturer_id',
    'medicine_code',
    'barcode',
    'brand_name',
    'generic_name',
    'strength',
    'dosage_form',
    'purchase_unit',
    'sale_unit',
    'units_per_purchase_unit',
    'reorder_level',
    'prescription_required',
    'batch_tracking_required',
    'expiry_tracking_required',
    'is_active',
    'notes',
])]
class Medicine extends Model
{
    use HasUlids;

    protected $attributes = [
        'purchase_unit' => 'pack',
        'sale_unit' => 'unit',
        'units_per_purchase_unit' => 1,
        'reorder_level' => 0,
        'prescription_required' => false,
        'batch_tracking_required' => true,
        'expiry_tracking_required' => true,
        'is_active' => true,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    protected function casts(): array
    {
        return [
            'units_per_purchase_unit' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'prescription_required' => 'boolean',
            'batch_tracking_required' => 'boolean',
            'expiry_tracking_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
