<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchases.manage') === true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['required', 'alpha', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:250'],
            'lines.*.medicine_id' => ['required', Rule::exists('medicines', 'id')->where('is_active', true)],
            'lines.*.ordered_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.landed_cost_allocated' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
