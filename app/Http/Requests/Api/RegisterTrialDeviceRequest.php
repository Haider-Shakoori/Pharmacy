<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTrialDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pharmacy_code' => ['required', 'alpha_dash:ascii', 'max:100'],
            'device_id' => ['required', 'uuid'],
            'platform' => ['nullable', 'string', 'in:android,windows'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'device_model' => ['nullable', 'string', 'max:160'],
            'os_version' => ['nullable', 'string', 'max:80'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'build_number' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
