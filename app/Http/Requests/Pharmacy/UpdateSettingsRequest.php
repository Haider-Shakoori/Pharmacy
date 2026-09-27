<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'require_counted_cash' => $this->boolean('require_counted_cash'),
            'allow_reopen' => $this->boolean('allow_reopen'),
            'require_close_before_next_day' => $this->boolean('require_close_before_next_day'),
            'block_online_sales_after_close' => $this->boolean('block_online_sales_after_close'),
            'warn_unsynced_devices_before_close' => $this->boolean('warn_unsynced_devices_before_close'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('pharmacy.locales'))],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],

            'business_day_rollover_time' => ['required', 'date_format:H:i'],
            'opening_cash_mode' => ['required', Rule::in(['carry_forward', 'manual'])],
            'require_counted_cash' => ['required', 'boolean'],
            'variance_note_threshold' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'allow_reopen' => ['required', 'boolean'],
            'require_close_before_next_day' => ['required', 'boolean'],
            'block_online_sales_after_close' => ['required', 'boolean'],
            'warn_unsynced_devices_before_close' => ['required', 'boolean'],
        ];
    }
}
