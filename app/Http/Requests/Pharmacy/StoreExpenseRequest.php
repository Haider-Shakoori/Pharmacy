<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('accounting.manage') === true;
    }

    public function rules(): array
    {
        return [
            'expense_account_id' => ['required', 'string', Rule::exists('ledger_accounts', 'id')->where('is_active', true)],
            'payment_account_id' => ['required', 'string', Rule::exists('ledger_accounts', 'id')->where('is_active', true)],
            'stock_location_id' => ['nullable', 'string', Rule::exists('stock_locations', 'id')->where('is_active', true)],
            'business_date' => ['required', 'date'],
            'currency' => ['required', 'alpha', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payee' => ['nullable', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }
}
