<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Str::slug((string) $this->input('code'), '_')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('roles', 'code')],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
