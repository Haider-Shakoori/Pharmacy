<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeBatchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.status') === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'quarantined', 'recalled', 'damaged'])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
