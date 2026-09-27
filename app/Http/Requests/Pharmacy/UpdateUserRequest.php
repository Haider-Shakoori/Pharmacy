<?php

namespace App\Http\Requests\Pharmacy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') === true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $userId = $this->route('user')?->getKey();

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
