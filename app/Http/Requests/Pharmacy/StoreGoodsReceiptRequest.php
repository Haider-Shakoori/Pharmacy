<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchases.manage') === true;
    }

    public function rules(): array
    {
        return [
            'received_at' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:120', 'unique:goods_receipts,idempotency_key'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:250'],
            'lines.*.purchase_order_line_id' => ['required', 'ulid', 'exists:purchase_order_lines,id'],
            'lines.*.received_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.bonus_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:120'],
            'lines.*.manufactured_at' => ['nullable', 'date'],
            'lines.*.expires_at' => ['nullable', 'date', 'after:lines.*.manufactured_at'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
