<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('branches', 'code')],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
