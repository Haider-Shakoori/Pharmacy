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
        string $platform = 'android',
    ): array {
        $license = $this->keys->findByPlainText($licenseKey);

        if ($license === null) {
            throw ValidationException::withMessages([
                'license_key' => 'The license key is invalid.',
            ]);
        }

        return $this->activateLicense(
            $license,
            $deviceId,
            $deviceName,
            $appVersion,
            $deviceModel,
            $osVersion,
            $buildNumber,
            $platform,
        );
    }

    public function activateLicense(
        License $license,
        string $deviceId,
        ?string $deviceName,
        ?string $appVersion,
        ?string $deviceModel = null,
        ?string $osVersion = null,
        ?string $buildNumber = null,
        string $platform = 'android',
    ): array {
        $platform = in_array($platform, ['android', 'windows'], true)
            ? $platform
            : 'android';

        return DB::transaction(function () use ($license, $deviceId, $deviceName, $appVersion, $deviceModel, $osVersion, $buildNumber, $platform): array {
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

            $deviceLimit = $platform === 'windows'
                ? $plan->max_windows_devices
                : $plan->max_android_devices;
            $platformLabel = $platform === 'windows' ? 'Windows PC' : 'Android device';

            if ($activation === null) {
                $activeDeviceCount = LicenseActivation::query()
                    ->where('license_id', $license->id)
                    ->where('platform', $platform)
                    ->whereNull('revoked_at')
                    ->count();

                if ($deviceLimit !== null && $activeDeviceCount >= $deviceLimit) {
                    throw ValidationException::withMessages([
                        'device_id' => "The {$platformLabel} limit for this subscription has been reached.",
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
                    ->where('platform', $platform)
                    ->whereNull('revoked_at')
                    ->count();

                if ($deviceLimit !== null && $activeDeviceCount >= $deviceLimit) {
                    throw ValidationException::withMessages([
                        'device_id' => "The {$platformLabel} limit for this subscription has been reached.",
                    ]);
                }

                $activation->revoked_at = null;
                $activation->activated_at = $now;
            }

            $activation->fill([
                'device_name' => $deviceName,
                'platform' => $platform,
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

            $entitlements = array_values(array_unique(array_filter(
                array_map(
                    static fn (mixed $feature): string => is_string($feature)
                        ? trim($feature)
                        : '',
                    $plan->features ?? [],
                ),
                static fn (string $feature): bool => $feature !== '',
            )));

            $subscriptionHealth = $this->health
                ->forSubscription($subscription)
                ->value;

            $payload = [
                'v' => 1,
                'entitlement_version' => 1,
                'tenant_id' => $subscription->business->tenant_id,
                'subscription_id' => $subscription->id,
                'license_id' => $license->id,
                'license_version' => $license->version,
                'activation_id' => $activation->id,
                'device_id' => $deviceId,
                'platform' => $platform,
                'plan_code' => $plan->code,
                'subscription_status' => $subscription->status->value,
                'subscription_health' => $subscriptionHealth,
                'trial_started_at' => $subscription->trial_started_at?->getTimestamp(),
                'trial_expires_at' => $subscription->trial_ends_at?->getTimestamp(),
                'subscription_expires_at' => $subscription->ends_at?->getTimestamp(),
                'issued_at' => $now->getTimestamp(),
                'server_time' => $now->getTimestamp(),
                'expires_at' => $leaseExpiresAt->getTimestamp(),
                'offline_valid_until' => $leaseExpiresAt->getTimestamp(),
                'entitlements' => $entitlements,
            ];

            return [
                'activation_id' => $activation->id,
                'lease_token' => $this->signer->sign($payload),
                'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                'subscription_health' => $subscriptionHealth,
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
                    'max_windows_devices' => $plan->max_windows_devices,
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
