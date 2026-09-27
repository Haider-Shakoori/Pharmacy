<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('tenant_id', $this->user()->tenant_id),
            ],
            'password' => ['required', 'string', 'min:8'],
            'preferred_locale' => ['required', Rule::in(config('pharmacy.locales'))],
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer'],
        ];
    }
}
