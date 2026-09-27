<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchases.pay') === true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'currency' => ['required', 'alpha', 'size:3'],
            'method' => ['required', Rule::in(['cash', 'bank', 'hawala', 'other'])],
            'reference' => ['nullable', 'string', 'max:160'],
            'paid_at' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
