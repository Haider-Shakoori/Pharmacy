<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'display_name',
    'phone',
    'address',
    'receipt_footer',
    'timezone',
    'locale',
    'currency',
    'daily_closing',
    'inventory_policy',
])]
class PharmacySetting extends Model
{
    protected $attributes = [
        'timezone' => 'Asia/Kabul',
        'locale' => 'en',
        'currency' => 'AFN',
    ];

    protected function casts(): array
    {
        return [
            'daily_closing' => 'array',
            'inventory_policy' => 'array',
        ];
    }
}
