<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RefreshDesktopLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'device_model' => ['nullable', 'string', 'max:160'],
            'os_version' => ['nullable', 'string', 'max:120'],
            'build_number' => ['nullable', 'string', 'max:80'],
        ];
    }
}
