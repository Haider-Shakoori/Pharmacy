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
        $businessId = $this->route('tenant')?->business?->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('businesses', 'slug')->ignore($businessId)],
            'contact_person' => ['required', 'string', 'max:160'],
            'phone_whatsapp' => ['required', 'string', 'max:64'],
            'location' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'locale' => ['required', Rule::in(config('pharmacy.locales'))],
        ];
    }
}
