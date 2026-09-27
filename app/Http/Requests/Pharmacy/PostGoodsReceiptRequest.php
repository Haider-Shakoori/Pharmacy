<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') === true;
    }

    public function rules(): array
    {
        return [
            'stock_location_id' => [
                'required',
                Rule::exists('stock_locations', 'id')->where('is_active', true),
            ],
        ];
    }
}
