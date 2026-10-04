<?php

namespace App\Services\Mobile;

use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\LicenseActivationService;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\OfflineLeaseSigner;
use App\Services\Settings\PharmacySettings;
use App\Services\Subscriptions\TrialProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileDeviceRegistrationService
{
    public function __construct(
        private readonly LicenseKeyService $keys,
        private readonly LicenseActivationService $activation,
        private readonly OfflineLeaseSigner $signer,
        private readonly PharmacySettings $settings,
        private readonly TrialProvisioner $trials,
    ) {}

    public function register(
        string $licenseKey,
        string $deviceId,
        string $email,
        string $password,
        ?string $deviceName,
        ?string $appVersion,
        ?string $deviceModel,
        ?string $osVersion,
        ?string $buildNumber,
        string $platform = 'android',
    ): array {
        $license = $this->keys->findByPlainText($licenseKey);

        if ($license === null) {
            throw ValidationException::withMessages([
                'license_key' => 'The license key is invalid.',
            ]);
        }

        $license->loadMissing('subscription.business.tenant');
        $tenant = $license->subscription?->business?->tenant;

        if (! $tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'license_key' => 'The license key is invalid.',
            ]);
        }

        $user = $this->authenticate($tenant, $email, $password);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'The provided pharmacy credentials are invalid.',
            ]);
        }

        return $this->completeRegistration(
            $license,
            $tenant,
            $user,
            $deviceId,
            $deviceName,
            $appVersion,
            $deviceModel,
            $osVersion,
            $buildNumber,
            $platform,
        );
    }

    public function registerTrial(
        Tenant $tenant,
        string $deviceId,
        string $email,
        string $password,
        ?string $deviceName,
        ?string $appVersion,
        ?string $deviceModel,
        ?string $osVersion,
        ?string $buildNumber,
        string $platform = 'android',
    ): array {
        $tenant->refresh()->loadMissing('business.subscription.license');

        if (
            $tenant->business === null
            || Str::lower((string) $tenant->business->owner_email) !== Str::lower(trim($email))
        ) {
            throw ValidationException::withMessages([
                'email' => 'The provided pharmacy credentials are invalid.',
            ]);
        }

        $user = $this->authenticate($tenant, $email, $password);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'The provided pharmacy credentials are invalid.',
            ]);
        }

        $subscription = $tenant->business->subscription;

        if ($subscription === null) {
            $this->trials->provision($tenant);
            $tenant->refresh()->load('business.subscription.license');
            $subscription = $tenant->business?->subscription;
        }

        if ($subscription === null || $subscription->status->value !== 'trial') {
            throw ValidationException::withMessages([
                'trial' => 'This pharmacy already has a subscription. Register with its license key instead.',
            ]);
        }

        if ($subscription->trial_ends_at === null || ! $subscription->trial_ends_at->isFuture()) {
            throw ValidationException::withMessages([
                'trial' => 'The seven-day trial has expired and cannot be restarted.',
            ]);
        }

        if ($subscription->license === null) {
            $this->keys->ensureForSubscription($subscription);
            $subscription->refresh()->load('license');
        }

        $license = $subscription->license;

        if (! $license instanceof License) {
            throw ValidationException::withMessages([
                'trial' => 'The trial license could not be issued.',
            ]);
        }

        return $this->completeRegistration(
            $license,
            $tenant,
            $user,
            $deviceId,
            $deviceName,
            $appVersion,
            $deviceModel,
            $osVersion,
            $buildNumber,
            $platform,
            allowExistingWindowsActivation: true,
        );
    }

    private function authenticate(Tenant $tenant, string $email, string $password): ?array
    {
        return $tenant->run(function () use ($email, $password): ?array {
            $user = User::query()
                ->with('roles.permissions')
                ->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])
                ->where('is_active', true)
                ->first();

            if ($user === null || ! Hash::check($password, $user->password)) {
                return null;
            }

            $permissions = $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('code'))
                ->unique()
                ->sort()
                ->values()
                ->all();

            $roles = $user->roles
                ->pluck('code')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $user->forceFill(['last_login_at' => now()])->save();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'permissions' => $permissions,
            ];
        });
    }

    private function completeRegistration(
        License $license,
        Tenant $tenant,
        array $user,
        string $deviceId,
        ?string $deviceName,
        ?string $appVersion,
        ?string $deviceModel,
        ?string $osVersion,
        ?string $buildNumber,
        string $platform,
        bool $allowExistingWindowsActivation = false,
    ): array {
        $activated = $this->activation->activateLicense(
            $license,
            $deviceId,
            $deviceName,
            $appVersion,
            $deviceModel,
            $osVersion,
            $buildNumber,
            $platform,
            $allowExistingWindowsActivation,
        );

        $now = CarbonImmutable::now();
        $expiresAt = CarbonImmutable::parse($activated['lease_expires_at']);

        $accessPayload = [
            'v' => 1,
            'purpose' => 'mobile_access',
            'tenant_id' => $activated['tenant']['id'],
            'activation_id' => $activated['activation_id'],
            'device_id' => $deviceId,
            'user_id' => $user['id'],
            'issued_at' => $now->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
        ];

        return [
            'access_token' => $this->signer->sign($accessPayload),
            'access_expires_at' => $expiresAt->toIso8601String(),
            'activation_id' => $activated['activation_id'],
            'device_id' => $deviceId,
            'tenant' => $activated['tenant'],
            'plan' => $activated['plan'],
            'subscription_health' => $activated['subscription_health'],
            'inventory_policy' => $this->settings->inventory($tenant),
            'offline_lease' => [
                'token' => $activated['lease_token'],
                'expires_at' => $activated['lease_expires_at'],
                'public_key' => (string) config('pharmacy.license.signing_public_key'),
            ],
            'user' => $user,
        ];
    }
}
