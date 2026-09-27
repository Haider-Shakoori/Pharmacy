<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchases.manage') === true;
    }

    public function rules(): array
    {
        return [
            'supplier_invoice_number' => ['nullable', 'string', 'max:120'],
            'goods_receipt_id' => ['nullable', 'ulid', 'exists:goods_receipts,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
