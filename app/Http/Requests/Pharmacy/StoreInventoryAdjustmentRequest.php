<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.adjust') === true;
    }

    public function rules(): array
    {
        return [
            'product_batch_id' => ['required', 'ulid', 'exists:product_batches,id'],
            'quantity_delta' => ['required', 'numeric', 'not_in:0', 'max:999999999', 'min:-999999999'],
            'reason_code' => [
                'required',
                Rule::in(['count_correction', 'damage', 'wastage', 'found_stock', 'other']),
            ],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
