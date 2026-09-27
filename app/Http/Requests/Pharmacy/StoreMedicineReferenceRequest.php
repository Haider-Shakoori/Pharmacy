<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicineReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medicines.manage') === true;
    }

    public function rules(): array
    {
        $type = $this->route('type');

        abort_unless(in_array($type, ['category', 'manufacturer'], true), 404);

        $table = $type === 'category' ? 'medicine_categories' : 'manufacturers';

        return [
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique($table, 'name'),
            ],
            'country' => [
                Rule::requiredIf($type === 'manufacturer'),
                'nullable', 'string', 'max:100',
            ],
        ];
    }
}
