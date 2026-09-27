<?php

namespace App\Services\Licensing;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\License;
use App\Models\LicenseActivation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseActivationService
{
    public function __construct(
        private readonly LicenseKeyService $keys,
        private readonly OfflineLeaseSigner $signer,
    ) {}

    public function activate(
        string $licenseKey,
        string $deviceId,
        ?string $deviceName,
        ?string $appVersion,
    ): array {
        $license = $this->keys->findByPlainText($licenseKey);

        if ($license === null) {
            throw ValidationException::withMessages([
                'license_key' => 'The license key is invalid.',
            ]);
        }

        return DB::transaction(function () use ($license, $deviceId, $deviceName, $appVersion): array {
            /** @var License $license */
            $license = License::query()
                ->with(['subscription.plan', 'subscription.tenant'])
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
                'last_seen_at' => $now,
            ]);
            $activation->save();

            $leaseExpiresAt = $now->addDays($plan->offline_grace_days);

            if ($subscription->ends_at !== null && $leaseExpiresAt->greaterThan($subscription->ends_at)) {
                $leaseExpiresAt = CarbonImmutable::instance($subscription->ends_at);
            }

            $payload = [
                'v' => 1,
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'license_id' => $license->id,
                'license_version' => $license->version,
                'activation_id' => $activation->id,
                'device_id' => $deviceId,
                'plan_code' => $plan->code,
                'issued_at' => $now->getTimestamp(),
                'expires_at' => $leaseExpiresAt->getTimestamp(),
            ];

            return [
                'lease_token' => $this->signer->sign($payload),
                'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                'tenant' => [
                    'id' => $subscription->tenant->id,
                    'name' => $subscription->tenant->name,
                    'slug' => $subscription->tenant->slug,
                    'timezone' => $subscription->tenant->timezone,
                    'currency' => $subscription->tenant->currency,
                    'locale' => $subscription->tenant->locale,
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
        $subscription = $license->subscription;
        $tenant = $subscription->tenant;
        $now = now();

        if ($license->status !== LicenseStatus::Active || $license->revoked_at !== null) {
            throw ValidationException::withMessages([
                'license_key' => 'The license has been revoked.',
            ]);
        }

        if ($tenant->status !== TenantStatus::Active) {
            throw ValidationException::withMessages([
                'license_key' => 'The pharmacy is not active.',
            ]);
        }

        if ($subscription->status !== SubscriptionStatus::Active) {
            throw ValidationException::withMessages([
                'license_key' => 'The subscription is not active.',
            ]);
        }

        if ($subscription->starts_at !== null && $subscription->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'license_key' => 'The subscription has not started yet.',
            ]);
        }

        if ($subscription->ends_at !== null && ! $subscription->ends_at->isFuture()) {
            throw ValidationException::withMessages([
                'license_key' => 'The subscription has expired.',
            ]);
        }
    }
}
