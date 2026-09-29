<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BootstrapLocalNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('pharmacy.local_node.enabled')
            && in_array($this->ip(), ['127.0.0.1', '::1'], true);
    }

    public function rules(): array
    {
        return [
            'registration' => ['required', 'array'],
            'registration.tenant_id' => ['required', 'string', 'max:64'],
            'registration.tenant_name' => ['required', 'string', 'max:160'],
            'registration.activation_id' => ['required', 'string', 'max:64'],
            'registration.device_id' => ['required', 'uuid'],
            'registration.access_token' => ['required', 'string', 'max:8192'],
            'registration.lease_token' => ['required', 'string', 'max:8192'],
            'registration.lease_public_key' => ['nullable', 'string', 'max:512'],
            'registration.lease_expires_at' => ['required', 'date'],
            'registration.user_id' => ['required', 'integer', 'min:1'],
            'registration.user_name' => ['required', 'string', 'max:160'],
            'registration.user_email' => ['required', 'email', 'max:255'],
            'registration.roles' => ['required', 'array', 'min:1'],
            'registration.roles.*' => ['string', 'max:64'],
            'registration.permissions' => ['array'],
            'registration.cloud_base_url' => ['nullable', 'url', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}