<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('accounting.manage') === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('ledger_accounts', 'code')],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'parent_id' => ['nullable', 'string', 'exists:ledger_accounts,id'],
            'currency' => ['required', 'alpha', 'size:3'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
