<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'alpha_dash:ascii',
                'max:100',
                Rule::unique('tenants', 'slug')->ignore($this->route('tenant')),
            ],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'locale' => ['required', Rule::in(config('pharmacy.locales'))],
        ];
    }
}
