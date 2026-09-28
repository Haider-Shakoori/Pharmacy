<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ActivateOfflineLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'max:120'],
            'installation_id' => ['required', 'uuid'],
            'machine_fingerprint_hash' => ['required', 'regex:/\\A[a-f0-9]{64}\\z/'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
