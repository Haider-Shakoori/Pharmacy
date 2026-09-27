<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('returns.manage') === true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:191'],
            'reason' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_line_id' => ['required', 'string', 'exists:sale_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'refunds' => ['required', 'array', 'min:1'],
            'refunds.*.method' => ['required', Rule::in(['cash', 'bank', 'mobile', 'credit'])],
            'refunds.*.amount' => ['required', 'numeric', 'gt:0'],
            'refunds.*.reference' => ['nullable', 'string', 'max:160'],
        ];
    }
}
