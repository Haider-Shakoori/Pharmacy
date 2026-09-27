<?php

namespace App\Http\Requests\Pharmacy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'prescription_required' => $this->boolean('prescription_required'),
            'batch_tracking_required' => $this->boolean('batch_tracking_required'),
            'expiry_tracking_required' => $this->boolean('expiry_tracking_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medicines.manage') === true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'medicine_category_id' => [
                'nullable',
                Rule::exists('medicine_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'manufacturer_id' => [
                'nullable',
                Rule::exists('manufacturers', 'id')->where('tenant_id', $tenantId),
            ],
            'medicine_code' => [
                'required', 'string', 'max:80',
                Rule::unique('medicines', 'medicine_code')->where('tenant_id', $tenantId),
            ],
            'barcode' => [
                'nullable', 'string', 'max:120',
                Rule::unique('medicines', 'barcode')->where('tenant_id', $tenantId),
            ],
            'brand_name' => ['required', 'string', 'max:180'],
            'generic_name' => ['nullable', 'string', 'max:180'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['nullable', 'string', 'max:80'],
            'purchase_unit' => ['required', 'string', 'max:50'],
            'sale_unit' => ['required', 'string', 'max:50'],
            'units_per_purchase_unit' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'prescription_required' => ['required', 'boolean'],
            'batch_tracking_required' => ['required', 'boolean'],
            'expiry_tracking_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
