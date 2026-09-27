<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchases.manage') === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('suppliers', 'code')->ignore($this->route('supplier'))],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:64'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
