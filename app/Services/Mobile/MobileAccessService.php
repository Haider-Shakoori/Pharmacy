<?php

namespace App\Services\Mobile;

use App\Enums\LicenseStatus;
use App\Models\LicenseActivation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\OfflineLeaseSigner;
use App\Services\Licensing\SignedTokenVerifier;
use App\Services\Subscriptions\SubscriptionHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class MobileAccessService
{
    public function __construct(
        private readonly SignedTokenVerifier $tokens,
        private readonly OfflineLeaseSigner $signer,
        private readonly SubscriptionHealthService $health,
    ) {}

    public function authenticate(
        Request $request,
        bool $allowExpired = false,
    ): MobileAccessContext {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            throw new AuthenticationException('A mobile access token is required.');
        }

        $payload = $this->tokens->verify(
            $token,
            purpose: 'mobile_access',
            allowExpired: $allowExpired,
        );

        foreach (['activation_id', 'tenant_id', 'device_id', 'user_id'] as $field) {
            if (! isset($payload[$field]) || $payload[$field] === '') {
                throw new AuthenticationException('The mobile access token is incomplete.');
            }
        }

        $activation = LicenseActivation::query()
            ->with([
                'license.subscription.plan',
                'license.subscription.business.tenant',
            ])
            ->find($payload['activation_id']);

        if ($activation === null || $activation->revoked_at !== null) {
            throw new AuthenticationException('This device activation is no longer valid.');
        }

        $license = $activation->license;
        $subscription = $license?->subscription;
        $tenant = $subscription?->business?->tenant;

        if (! $tenant instanceof Tenant ||
            $license === null ||
            $subscription === null ||
            $license->status !== LicenseStatus::Active ||
            $license->revoked_at !== null ||
            (string) $tenant->id !== (string) $payload['tenant_id'] ||
            (string) $activation->device_id !== (string) $payload['device_id']) {
            throw new AuthenticationException('The mobile access token no longer matches an active subscription.');
        }

        $health = $this->health->forSubscription($subscription);

        if (! $health->isOperational()) {
            throw new AuthenticationException(
                'The pharmacy subscription is not operational: '.$health->value.'.',
            );
        }

        $userSnapshot = $tenant->run(function () use ($payload): ?array {
            $user = User::query()
                ->with('roles.permissions')
                ->whereKey((int) $payload['user_id'])
                ->where('is_active', true)
                ->first();

            if ($user === null) {
                return null;
            }

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
        });

        if ($userSnapshot === null) {
            throw new AuthenticationException('The pharmacy user is no longer active.');
        }

        $activation->forceFill(['last_seen_at' => now()])->save();

        return new MobileAccessContext(
            tenant: $tenant,
            activation: $activation,
            userId: (int) $userSnapshot['id'],
            user: $userSnapshot,
            permissions: $userSnapshot['permissions'],
        );
    }

    public function refresh(MobileAccessContext $context): array
    {
        $activation = $context->activation->fresh([
            'license.subscription.plan',
            'license.subscription.business.tenant',
        ]);

        if ($activation === null || $activation->revoked_at !== null) {
            throw new AuthenticationException('This device activation is no longer valid.');
        }

        $license = $activation->license;
        $subscription = $license->subscription;
        $plan = $subscription->plan;
        $health = $this->health->forSubscription($subscription);

        if (! $health->isOperational()) {
            throw new AuthenticationException(
                'The pharmacy subscription is not operational: '.$health->value.'.',
            );
        }

        $now = CarbonImmutable::now();
        $leaseExpiresAt = $now->addDays($plan->offline_grace_days);
        $effectiveEnd = $subscription->status->value === 'trial'
            ? $subscription->trial_ends_at
            : $subscription->ends_at;

        if ($effectiveEnd !== null &&
            $leaseExpiresAt->greaterThan($effectiveEnd)) {
            $leaseExpiresAt = CarbonImmutable::instance($effectiveEnd);
        }

        $leasePayload = [
            'v' => 1,
            'tenant_id' => $context->tenant->id,
            'subscription_id' => $subscription->id,
            'license_id' => $license->id,
            'license_version' => $license->version,
            'activation_id' => $activation->id,
            'device_id' => $activation->device_id,
            'plan_code' => $plan->code,
            'subscription_status' => $subscription->status->value,
            'issued_at' => $now->getTimestamp(),
            'expires_at' => $leaseExpiresAt->getTimestamp(),
        ];

        $accessPayload = [
            'v' => 1,
            'purpose' => 'mobile_access',
            'tenant_id' => $context->tenant->id,
            'activation_id' => $activation->id,
            'device_id' => $activation->device_id,
            'user_id' => $context->userId,
            'issued_at' => $now->getTimestamp(),
            'expires_at' => $leaseExpiresAt->getTimestamp(),
        ];

        return [
            'access_token' => $this->signer->sign($accessPayload),
            'device_id' => $activation->device_id,
            'access_expires_at' => $leaseExpiresAt->toIso8601String(),
            'subscription_health' => $health->value,
            'offline_lease' => [
                'token' => $this->signer->sign($leasePayload),
                'expires_at' => $leaseExpiresAt->toIso8601String(),
                'public_key' => (string) config('pharmacy.license.signing_public_key'),
            ],
            'user' => $context->user,
        ];
    }
}
