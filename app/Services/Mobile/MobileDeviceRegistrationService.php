<?php

namespace App\Services\Mobile;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\LicenseActivationService;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\OfflineLeaseSigner;
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

        $user = $tenant->run(function () use ($email, $password): ?array {
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

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'The provided pharmacy credentials are invalid.',
            ]);
        }

        $activated = $this->activation->activate(
            $licenseKey,
            $deviceId,
            $deviceName,
            $appVersion,
            $deviceModel,
            $osVersion,
            $buildNumber,
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
            'offline_lease' => [
                'token' => $activated['lease_token'],
                'expires_at' => $activated['lease_expires_at'],
                'public_key' => (string) config('pharmacy.license.signing_public_key'),
            ],
            'user' => $user,
        ];
    }
}
