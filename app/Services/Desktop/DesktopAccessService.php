<?php

namespace App\Services\Desktop;

use App\Enums\LicenseStatus;
use App\Models\LicenseActivation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\OfflineLeaseSigner;
use App\Services\Licensing\SignedTokenVerifier;
use App\Services\Subscriptions\SubscriptionHealthService;
use App\Services\Sync\SyncAccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DesktopAccessService
{
    public function __construct(
        private readonly SignedTokenVerifier $tokens,
        private readonly OfflineLeaseSigner $signer,
        private readonly SubscriptionHealthService $health,
    ) {}

    public function login(
        string $leaseToken,
        string $deviceId,
        string $email,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $lease = $this->tokens->verify(
            $leaseToken,
            purpose: 'offline_lease',
        );

        foreach ([
            'activation_id',
            'tenant_id',
            'license_id',
            'license_version',
            'device_id',
            'platform',
        ] as $field) {
            if (! isset($lease[$field]) || $lease[$field] === '') {
                throw new AuthenticationException('The desktop activation lease is incomplete.');
            }
        }

        if ($lease['platform'] !== 'windows' ||
            (string) $lease['device_id'] !== $deviceId) {
            throw new AuthenticationException('The desktop activation lease does not belong to this Windows installation.');
        }

        [$activation, $tenant] = $this->resolveActivation(
            activationId: $lease['activation_id'],
            tenantId: $lease['tenant_id'],
            deviceId: $deviceId,
            licenseId: $lease['license_id'],
            licenseVersion: (int) $lease['license_version'],
        );

        $user = $this->authenticateUser($tenant, $email, $password);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'The provided pharmacy credentials are invalid.',
            ]);
        }

        return $this->issueSession(
            $activation,
            $tenant,
            $user,
            $ipAddress,
            $userAgent,
        );
    }

    public function authenticate(Request $request): SyncAccessContext
    {
        $accessToken = $request->bearerToken();

        if (! is_string($accessToken) || $accessToken === '') {
            throw new AuthenticationException('A desktop user session is required.');
        }

        $payload = $this->tokens->verify(
            $accessToken,
            purpose: 'desktop_access',
        );

        foreach (['activation_id', 'tenant_id', 'device_id', 'user_id'] as $field) {
            if (! isset($payload[$field]) || $payload[$field] === '') {
                throw new AuthenticationException('The desktop user session is incomplete.');
            }
        }

        [$activation, $tenant] = $this->resolveActivation(
            activationId: $payload['activation_id'],
            tenantId: $payload['tenant_id'],
            deviceId: (string) $payload['device_id'],
        );

        $user = $this->findActiveUser($tenant, (int) $payload['user_id']);

        if ($user === null) {
            throw new AuthenticationException('The pharmacy user is no longer active.');
        }

        $tokenSessionVersion = max(
            1,
            (int) ($payload['session_version'] ?? 1),
        );

        if ((int) $activation->session_version !== $tokenSessionVersion) {
            throw new AuthenticationException(
                'This desktop session was signed out by platform support.',
            );
        }

        $activation->forceFill([
            'last_seen_at' => now(),
            'last_session_activity_at' => now(),
            'last_ip_address' => $request->ip(),
            'last_user_agent' => $request->userAgent(),
        ])->save();

        return new SyncAccessContext(
            tenant: $tenant,
            activation: $activation,
            userId: (int) $user['id'],
            user: $user,
            permissions: $user['permissions'],
        );
    }

    public function refresh(
        string $accessToken,
        string $deviceId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $payload = $this->tokens->verify(
            $accessToken,
            purpose: 'desktop_access',
        );

        foreach (['activation_id', 'tenant_id', 'device_id', 'user_id'] as $field) {
            if (! isset($payload[$field]) || $payload[$field] === '') {
                throw new AuthenticationException('The desktop user session is incomplete.');
            }
        }

        if ((string) $payload['device_id'] !== $deviceId) {
            throw new AuthenticationException('The desktop user session does not belong to this Windows installation.');
        }

        [$activation, $tenant] = $this->resolveActivation(
            activationId: $payload['activation_id'],
            tenantId: $payload['tenant_id'],
            deviceId: $deviceId,
        );

        $user = $this->findActiveUser($tenant, (int) $payload['user_id']);

        if ($user === null) {
            throw new AuthenticationException('The pharmacy user is no longer active.');
        }

        $tokenSessionVersion = max(
            1,
            (int) ($payload['session_version'] ?? 1),
        );

        if ((int) $activation->session_version !== $tokenSessionVersion) {
            throw new AuthenticationException(
                'This desktop session was signed out by platform support.',
            );
        }

        return $this->issueSession(
            $activation,
            $tenant,
            $user,
            $ipAddress,
            $userAgent,
        );
    }

    private function resolveActivation(
        string|int $activationId,
        string|int $tenantId,
        string $deviceId,
        string|int|null $licenseId = null,
        ?int $licenseVersion = null,
    ): array {
        $activation = LicenseActivation::query()
            ->with([
                'license.subscription.plan',
                'license.subscription.business.tenant',
            ])
            ->find($activationId);

        if ($activation === null ||
            $activation->revoked_at !== null ||
            $activation->platform !== 'windows') {
            throw new AuthenticationException('This desktop activation is no longer valid.');
        }

        $license = $activation->license;
        $subscription = $license?->subscription;
        $tenant = $subscription?->business?->tenant;

        if (! $tenant instanceof Tenant ||
            $license === null ||
            $subscription === null ||
            $license->status !== LicenseStatus::Active ||
            $license->revoked_at !== null ||
            (string) $tenant->id !== (string) $tenantId ||
            (string) $activation->device_id !== $deviceId ||
            ($licenseId !== null && (string) $license->id !== (string) $licenseId) ||
            ($licenseVersion !== null && (int) $license->version !== $licenseVersion)) {
            throw new AuthenticationException('The desktop activation no longer matches an active pharmacy subscription.');
        }

        $health = $this->health->forSubscription($subscription);

        if (! $health->isOperational()) {
            throw new AuthenticationException(
                'The pharmacy subscription is not operational: '.$health->value.'.',
            );
        }

        return [$activation, $tenant];
    }

    private function authenticateUser(Tenant $tenant, string $email, string $password): ?array
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

            $user->forceFill(['last_login_at' => now()])->save();

            return $this->snapshotUser($user);
        });
    }

    private function findActiveUser(Tenant $tenant, int $userId): ?array
    {
        return $tenant->run(function () use ($userId): ?array {
            $user = User::query()
                ->with('roles.permissions')
                ->whereKey($userId)
                ->where('is_active', true)
                ->first();

            return $user === null ? null : $this->snapshotUser($user);
        });
    }

    private function snapshotUser(User $user): array
    {
        $roles = $user->roles
            ->pluck('code')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('code'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    private function issueSession(
        LicenseActivation $activation,
        Tenant $tenant,
        array $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $subscription = $activation->license->subscription;
        $plan = $subscription->plan;
        $health = $this->health->forSubscription($subscription);
        $now = CarbonImmutable::now();
        $expiresAt = $now->addDays($plan->offline_grace_days);
        $effectiveEnd = $subscription->status->value === 'trial'
            ? $subscription->trial_ends_at
            : $subscription->ends_at;

        if ($effectiveEnd !== null && $expiresAt->greaterThan($effectiveEnd)) {
            $expiresAt = CarbonImmutable::instance($effectiveEnd);
        }

        if ($expiresAt->lessThanOrEqualTo($now)) {
            throw new AuthenticationException('The pharmacy subscription no longer permits an offline desktop session.');
        }

        $payload = [
            'v' => 1,
            'purpose' => 'desktop_access',
            'tenant_id' => $tenant->id,
            'activation_id' => $activation->id,
            'device_id' => $activation->device_id,
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'session_version' => max(1, (int) $activation->session_version),
            'roles' => $user['roles'],
            'permissions' => $user['permissions'],
            'issued_at' => $now->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
        ];

        $activation->forceFill([
            'current_user_id' => $user['id'],
            'current_user_name' => $user['name'],
            'current_user_email' => $user['email'],
            'session_issued_at' => $now,
            'session_expires_at' => $expiresAt,
            'last_session_activity_at' => $now,
            'last_ip_address' => $ipAddress,
            'last_user_agent' => $userAgent,
            'last_seen_at' => $now,
        ])->save();

        return [
            'access_token' => $this->signer->sign($payload),
            'access_expires_at' => $expiresAt->toIso8601String(),
            'subscription_health' => $health->value,
            'user' => $user,
        ];
    }
}
