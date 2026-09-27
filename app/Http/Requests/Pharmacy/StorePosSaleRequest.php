<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pos.sell') === true;
    }

    public function rules(): array
    {
        return [
            'stock_location_id' => ['required', 'string', Rule::exists('stock_locations', 'id')->where('is_active', true)],
            'customer_id' => ['nullable', 'string', Rule::exists('customers', 'id')->where('is_active', true)],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.medicine_id' => ['required', 'string', Rule::exists('medicines', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', Rule::in(['cash', 'bank', 'mobile', 'credit'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payments.*.reference' => ['nullable', 'string', 'max:160'],
        ];
    }
}
