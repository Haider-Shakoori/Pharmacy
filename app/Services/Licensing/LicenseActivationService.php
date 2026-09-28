<?php

namespace App\Services\Licensing;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionHealth;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Services\Subscriptions\SubscriptionHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseActivationService
{
    public function __construct(
        private readonly LicenseKeyService $keys,
        private readonly OfflineLeaseSigner $signer,
        private readonly SubscriptionHealthService $health,
    ) {}

    public function activate(
        string $licenseKey,
        string $deviceId,
        ?string $deviceName,
        ?string $appVersion,
        ?string $deviceModel = null,
        ?string $osVersion = null,
        ?string $buildNumber = null,
    ): array {
        $license = $this->keys->findByPlainText($licenseKey);

        if ($license === null) {
            throw ValidationException::withMessages([
                'license_key' => 'The license key is invalid.',
            ]);
        }

        return DB::transaction(function () use ($license, $deviceId, $deviceName, $appVersion, $deviceModel, $osVersion, $buildNumber): array {
            /** @var License $license */
            $license = License::query()
                ->with(['subscription.plan', 'subscription.business.tenant'])
                ->lockForUpdate()
                ->findOrFail($license->id);

            $this->assertLicenseUsable($license);

            $subscription = $license->subscription;
            $plan = $subscription->plan;
            $now = CarbonImmutable::now();

            $activation = LicenseActivation::query()
                ->where('license_id', $license->id)
                ->where('device_id', $deviceId)
                ->first();

            if ($activation === null) {
                $activeDeviceCount = LicenseActivation::query()
                    ->where('license_id', $license->id)
                    ->whereNull('revoked_at')
                    ->count();

                if ($plan->max_android_devices !== null && $activeDeviceCount >= $plan->max_android_devices) {
                    throw ValidationException::withMessages([
                        'device_id' => 'The Android device limit for this subscription has been reached.',
                    ]);
                }

                $activation = new LicenseActivation([
                    'license_id' => $license->id,
                    'device_id' => $deviceId,
                    'activated_at' => $now,
                ]);
            } elseif ($activation->revoked_at !== null) {
                $activeDeviceCount = LicenseActivation::query()
                    ->where('license_id', $license->id)
                    ->whereNull('revoked_at')
                    ->count();

                if ($plan->max_android_devices !== null && $activeDeviceCount >= $plan->max_android_devices) {
                    throw ValidationException::withMessages([
                        'device_id' => 'The Android device limit for this subscription has been reached.',
                    ]);
                }

                $activation->revoked_at = null;
                $activation->activated_at = $now;
            }

            $activation->fill([
                'device_name' => $deviceName,
                'platform' => 'android',
                'app_version' => $appVersion,
                'device_model' => $deviceModel,
                'os_version' => $osVersion,
                'build_number' => $buildNumber,
                'last_seen_at' => $now,
            ]);
            $activation->save();

            $leaseExpiresAt = $now->addDays($plan->offline_grace_days);
            $effectiveEnd = $subscription->ends_at;

            if ($subscription->status->value === 'trial') {
                $effectiveEnd = $subscription->trial_ends_at;
            }

            if ($effectiveEnd !== null && $leaseExpiresAt->greaterThan($effectiveEnd)) {
                $leaseExpiresAt = CarbonImmutable::instance($effectiveEnd);
            }

            $payload = [
                'v' => 1,
                'tenant_id' => $subscription->business->tenant_id,
                'subscription_id' => $subscription->id,
                'license_id' => $license->id,
                'license_version' => $license->version,
                'activation_id' => $activation->id,
                'device_id' => $deviceId,
                'plan_code' => $plan->code,
                'subscription_status' => $subscription->status->value,
                'issued_at' => $now->getTimestamp(),
                'expires_at' => $leaseExpiresAt->getTimestamp(),
            ];

            return [
                'activation_id' => $activation->id,
                'lease_token' => $this->signer->sign($payload),
                'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                'subscription_health' => $this->health->forSubscription($subscription)->value,
                'tenant' => [
                    'id' => $subscription->business->tenant->id,
                    'name' => $subscription->business->pharmacy_name,
                    'slug' => $subscription->business->slug,
                    'cloud_base_url' => 'https://'.$subscription->business->slug.'.'.config('pharmacy.deployment_host'),
                    'timezone' => $subscription->business->default_timezone,
                    'currency' => $subscription->business->billing_currency,
                    'locale' => $subscription->business->default_locale,
                ],
                'plan' => [
                    'code' => $plan->code,
                    'max_android_devices' => $plan->max_android_devices,
                    'offline_grace_days' => $plan->offline_grace_days,
                    'features' => $plan->features ?? [],
                ],
            ];
        });
    }

    private function assertLicenseUsable(License $license): void
    {
        if ($license->status !== LicenseStatus::Active || $license->revoked_at !== null) {
            throw ValidationException::withMessages([
                'license_key' => 'The license has been revoked.',
            ]);
        }

        $health = $this->health->forSubscription($license->subscription);

        if (! in_array($health, [
            SubscriptionHealth::Healthy,
            SubscriptionHealth::Trial,
            SubscriptionHealth::Expiring,
        ], true)) {
            throw ValidationException::withMessages([
                'license_key' => 'The subscription is not operational: '.$health->value.'.',
            ]);
        }
    }
}
