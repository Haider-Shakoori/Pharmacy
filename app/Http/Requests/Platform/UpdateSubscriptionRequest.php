<?php

namespace App\Http\Requests\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSubscriptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_renew' => $this->boolean('auto_renew'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'exists:plans,id'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'auto_renew' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $planId = (string) $this->input('plan_id');

                if ($planId === '') {
                    return;
                }

                $plan = Plan::query()->find($planId);

                if ($plan !== null && ! $plan->is_active) {
                    $validator->errors()->add('plan_id', 'Inactive plans cannot be assigned to pharmacies.');
                }
            },
        ];
    }
}
