<?php

namespace App\Services\Licensing;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionHealth;
use App\Enums\SubscriptionStatus;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Services\Subscriptions\SubscriptionHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfflineInstallationActivationService
{
    public function __construct(
        private readonly LicenseKeyService $keys,
        private readonly OfflineLeaseSigner $signer,
        private readonly SubscriptionHealthService $health,
    ) {}

    public function activate(
        string $licenseKey,
        string $installationId,
        string $machineFingerprintHash,
        ?string $deviceName,
        ?string $appVersion,
    ): array {
        $license = $this->keys->findByPlainText($licenseKey);

        if ($license === null) {
            throw ValidationException::withMessages([
                'license_key' => 'The offline license key is invalid.',
            ]);
        }

        return DB::connection('central')->transaction(function () use (
            $license,
            $installationId,
            $machineFingerprintHash,
            $deviceName,
            $appVersion,
        ): array {
            /** @var License $license */
            $license = License::query()
                ->with(['subscription.plan', 'subscription.business.tenant'])
                ->lockForUpdate()
                ->findOrFail($license->id);

            $this->assertLicenseUsable($license);

            $subscription = $license->subscription;
            $plan = $subscription->plan;

            if (! (bool) data_get($plan->features ?? [], 'offline_windows', false)) {
                throw ValidationException::withMessages([
                    'license_key' => 'This subscription is not licensed for BusinessOS Pharmacy Offline.',
                ]);
            }

            $now = CarbonImmutable::now();
            $expiresAt = $subscription->status === SubscriptionStatus::Trial
                ? $subscription->trial_ends_at
                : $subscription->ends_at;

            if ($expiresAt === null) {
                throw ValidationException::withMessages([
                    'license_key' => 'Offline licenses require an explicit expiry date.',
                ]);
            }

            $expiresAt = CarbonImmutable::instance($expiresAt);

            if (! $expiresAt->isFuture()) {
                throw ValidationException::withMessages([
                    'license_key' => 'The offline license has already expired.',
                ]);
            }

            $activation = LicenseActivation::query()
                ->where('license_id', $license->id)
                ->where('device_id', $installationId)
                ->first();

            if ($activation !== null &&
                $activation->revoked_at === null &&
                $activation->machine_fingerprint_hash !== null &&
                ! hash_equals($activation->machine_fingerprint_hash, $machineFingerprintHash)) {
                throw ValidationException::withMessages([
                    'installation_id' => 'This installation ID is already bound to another machine.',
                ]);
            }

            if ($activation === null || $activation->revoked_at !== null) {
                $activeOfflineCount = LicenseActivation::query()
                    ->where('license_id', $license->id)
                    ->where('platform', 'windows_offline')
                    ->whereNull('revoked_at')
                    ->when(
                        $activation !== null,
                        fn ($query) => $query->whereKeyNot($activation->id),
                    )
                    ->count();

                $limit = (int) config('pharmacy.offline.max_installations_per_license', 1);

                if ($activeOfflineCount >= $limit) {
                    throw ValidationException::withMessages([
                        'installation_id' => 'The offline installation limit for this license has been reached.',
                    ]);
                }
            }

            if ($activation === null) {
                $activation = new LicenseActivation([
                    'license_id' => $license->id,
                    'device_id' => $installationId,
                    'activated_at' => $now,
                ]);
            } else {
                $activation->revoked_at = null;
                $activation->activated_at = $now;
            }

            $activation->fill([
                'device_name' => $deviceName,
                'device_model' => 'BusinessOS Pharmacy Offline',
                'platform' => 'windows_offline',
                'app_version' => $appVersion,
                'machine_fingerprint_hash' => $machineFingerprintHash,
                'last_seen_at' => $now,
            ]);
            $activation->save();

            $payload = [
                'v' => 1,
                'purpose' => 'offline_installation',
                'product' => 'businessos-pharmacy',
                'edition' => 'offline',
                'tenant_id' => $subscription->business->tenant_id,
                'business_id' => $subscription->business_id,
                'subscription_id' => $subscription->id,
                'license_id' => $license->id,
                'license_version' => $license->version,
                'activation_id' => $activation->id,
                'installation_id' => $installationId,
                'machine_fingerprint_hash' => $machineFingerprintHash,
                'pharmacy_name' => $subscription->business->pharmacy_name,
                'plan_code' => $plan->code,
                'features' => $plan->features ?? [],
                'issued_at' => $now->getTimestamp(),
                'expires_at' => $expiresAt->getTimestamp(),
            ];

            return [
                'license_token' => $this->signer->sign($payload),
                'expires_at' => $expiresAt->toIso8601String(),
                'subscription_health' => $this->health->forSubscription($subscription)->value,
                'pharmacy' => [
                    'name' => $subscription->business->pharmacy_name,
                    'slug' => $subscription->business->slug,
                ],
                'plan' => [
                    'code' => $plan->code,
                    'features' => $plan->features ?? [],
                ],
            ];
        });
    }

    private function assertLicenseUsable(License $license): void
    {
        if ($license->status !== LicenseStatus::Active || $license->revoked_at !== null) {
            throw ValidationException::withMessages([
                'license_key' => 'The offline license has been revoked.',
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
