<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', 'unique:businesses,slug'],
            'contact_person' => ['required', 'string', 'max:160'],
            'phone_whatsapp' => ['required', 'string', 'max:64'],
            'location' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'locale' => ['required', Rule::in(config('pharmacy.locales'))],
            'owner_name' => ['required', 'string', 'max:160'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
