<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    protected function prepareForValidation(): void
    {
        $values = [
            'desktop_cloud_sync_enabled' => $this->boolean('desktop_cloud_sync_enabled'),
        ];

        if ($this->has('slug')) {
            $values['slug'] = strtolower(trim((string) $this->input('slug')));
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        $businessId = $this->route('tenant')?->business?->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(config('pharmacy.reserved_subdomains', [])),
                Rule::unique('businesses', 'slug')->ignore($businessId),
            ],
            'contact_person' => ['required', 'string', 'max:160'],
            'phone_whatsapp' => ['required', 'string', 'max:64'],
            'location' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'locale' => ['required', Rule::in(config('pharmacy.locales'))],
            'desktop_cloud_sync_enabled' => ['required', 'boolean'],
        ];
    }
}
