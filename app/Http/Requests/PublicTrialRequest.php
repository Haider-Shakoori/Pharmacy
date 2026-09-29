<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PublicTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pharmacy_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:160'],
            'owner_email' => ['required', 'email', 'max:255'],
            'phone_whatsapp' => ['required', 'string', 'max:64'],
            'location' => ['required', 'string', 'max:255'],
            'preferred_locale' => ['required', Rule::in(config('pharmacy.locales'))],
            'notes' => ['nullable', 'string', 'max:1500'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }
}
