<?php

namespace App\Http\Requests\Platform;

use App\Enums\BillingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper((string) $this->input('code')),
            'currency' => Str::upper((string) $this->input('currency', 'AFN')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash:ascii', 'max:60', 'unique:plans,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'alpha', 'size:3'],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_android_devices' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_windows_devices' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_branches' => ['required', 'integer', 'min:1', 'max:1000000'],
            'offline_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:80'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
