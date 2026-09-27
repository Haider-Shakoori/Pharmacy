<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') === true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'ulid', Rule::exists('branches', 'id')->where('is_active', true)],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('stock_locations', 'code')->where('branch_id', $this->input('branch_id')),
            ],
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(['store', 'warehouse', 'shelf', 'quarantine', 'other'])],
        ];
    }
}
