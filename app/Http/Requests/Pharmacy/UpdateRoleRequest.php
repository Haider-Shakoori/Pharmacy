<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') === true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
