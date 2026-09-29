<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BootstrapLocalNodeRequest;
use App\Models\Business;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LocalNodeState;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Licensing\LocalNodeLeaseGuard;
use App\Services\Licensing\SignedTokenVerifier;
use App\Services\Settings\PharmacySettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class LocalNodeBootstrapController extends Controller
{
    public function __invoke(
        BootstrapLocalNodeRequest $request,
        SignedTokenVerifier $tokens,
        TenantDatabaseProvisioner $databases,
        RbacProvisioner $rbac,
        PharmacySettings $settings,
        InventoryProvisioner $inventory,
        AccountingProvisioner $accounting,
        LocalNodeLeaseGuard $leaseGuard,
    ): JsonResponse {
        $registration = $request->array('registration');
        $cloudData = $this->verifyWithCloud($registration);
        $cloudUser = is_array($cloudData['user'] ?? null)
            ? $cloudData['user']
            : [];
        $roles = collect($cloudUser['roles'] ?? [])
            ->map(fn ($role) => Str::lower((string) $role))
            ->all();

        if (! array_intersect($roles, ['owner', 'administrator'])) {
            throw ValidationException::withMessages([
                'registration.roles' => 'Owner or administrator credentials are required to activate a local Windows server.',
            ]);
        }

        $offlineLease = is_array($cloudData['offline_lease'] ?? null)
            ? $cloudData['offline_lease']
            : [];
        $leaseToken = (string) ($offlineLease['token'] ?? '');
        $leasePublicKey = (string) ($offlineLease['public_key'] ?? '');

        if ($leaseToken === '' || $leasePublicKey === '') {
            throw ValidationException::withMessages([
                'registration' => 'BusinessOS licensing did not return a valid offline lease.',
            ]);
        }

        $payload = $tokens->verifyWithPublicKey(
            $leaseToken,
            $leasePublicKey,
        );

        foreach ([
            'tenant_id' => $registration['tenant_id'],
            'activation_id' => $registration['activation_id'],
            'device_id' => $registration['device_id'],
        ] as $field => $expected) {
            if ((string) ($payload[$field] ?? '') !== (string) $expected) {
                throw ValidationException::withMessages([
                    'registration' => "The signed license {$field} does not match this registration.",
                ]);
            }
        }

        if ((string) ($cloudData['device_id'] ?? '') !== (string) $registration['device_id']) {
            throw ValidationException::withMessages([
                'registration.device_id' => 'BusinessOS licensing returned a different Windows device.',
            ]);
        }

        if ((string) ($cloudUser['id'] ?? '') !== (string) $registration['user_id']) {
            throw ValidationException::withMessages([
                'registration.user_id' => 'BusinessOS licensing returned a different pharmacy user.',
            ]);
        }

        $registration['user_name'] = (string) ($cloudUser['name'] ?? $registration['user_name']);
        $registration['user_email'] = (string) ($cloudUser['email'] ?? $registration['user_email']);
        $registration['roles'] = $roles;
        $registration['permissions'] = $cloudUser['permissions'] ?? [];
        $registration['lease_token'] = $leaseToken;
        $registration['lease_public_key'] = $leasePublicKey;

        $issuedAt = CarbonImmutable::createFromTimestampUTC((int) $payload['issued_at']);
        $expiresAt = CarbonImmutable::createFromTimestampUTC((int) $payload['expires_at']);
        $status = (string) ($payload['subscription_status'] ?? 'active');
        $subscriptionStatus = $status === SubscriptionStatus::Trial->value
            ? SubscriptionStatus::Trial
            : SubscriptionStatus::Active;

        $tenant = DB::connection('central')->transaction(function () use (
            $registration,
            $payload,
            $issuedAt,
            $expiresAt,
            $subscriptionStatus,
        ): Tenant {
            $tenant = Tenant::query()->updateOrCreate(
                ['id' => (string) $registration['tenant_id']],
                [
                    'status' => 'active',
                    'provisioning_status' => 'application_ready',
                    'provisioning_error' => null,
                ],
            );

            $cloudUrl = (string) ($registration['cloud_base_url'] ?? '');
            $host = parse_url($cloudUrl, PHP_URL_HOST);
            $slug = is_string($host) && str_contains($host, '.')
                ? explode('.', $host)[0]
                : Str::slug((string) $registration['tenant_name']);

            $business = Business::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'pharmacy_name' => (string) $registration['tenant_name'],
                    'slug' => $slug !== '' ? $slug : 'local-pharmacy',
                    'owner_email' => Str::lower((string) $registration['user_email']),
                    'billing_currency' => 'AFN',
                    'default_timezone' => 'Asia/Kabul',
                    'default_locale' => 'en',
                ],
            );

            $planCode = (string) ($payload['plan_code'] ?? 'LOCAL');
            $plan = Plan::query()->firstOrCreate(
                ['code' => $planCode],
                [
                    'name' => $planCode.' Local Mirror',
                    'description' => 'Local Windows mirror of the BusinessOS subscription.',
                    'price' => 0,
                    'currency' => 'AFN',
                    'billing_period' => 'monthly',
                    'max_branches' => 1,
                    'offline_grace_days' => max(1, (int) ceil($issuedAt->diffInDays($expiresAt))),
                    'features' => [],
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            $subscription = Subscription::query()->updateOrCreate(
                ['id' => (string) $payload['subscription_id']],
                [
                    'business_id' => $business->id,
                    'plan_id' => $plan->id,
                    'status' => $subscriptionStatus,
                    'trial_started_at' => $subscriptionStatus === SubscriptionStatus::Trial ? $issuedAt : null,
                    'trial_ends_at' => $subscriptionStatus === SubscriptionStatus::Trial ? $expiresAt : null,
                    'starts_at' => $issuedAt,
                    'ends_at' => $subscriptionStatus === SubscriptionStatus::Trial ? null : $expiresAt,
                    'auto_renew' => false,
                    'notes' => 'Local Windows mirror; cloud-signed lease is authoritative.',
                ],
            );

            $license = License::query()->updateOrCreate(
                ['id' => (string) $payload['license_id']],
                [
                    'subscription_id' => $subscription->id,
                    'key_hash' => hash('sha256', 'local-node|'.(string) $payload['license_id']),
                    'key_hint' => 'LOCAL-NODE',
                    'status' => 'active',
                    'version' => (int) ($payload['license_version'] ?? 1),
                    'generated_at' => $issuedAt,
                    'revoked_at' => null,
                ],
            );

            LicenseActivation::query()->updateOrCreate(
                ['id' => (string) $registration['activation_id']],
                [
                    'license_id' => $license->id,
                    'device_id' => (string) $registration['device_id'],
                    'device_name' => 'BusinessOS Pharmacy Windows',
                    'platform' => 'windows',
                    'activated_at' => $issuedAt,
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ],
            );

            LocalNodeState::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'activation_id' => (string) $registration['activation_id'],
                    'device_id' => (string) $registration['device_id'],
                    'lease_token' => (string) $registration['lease_token'],
                    'lease_public_key' => (string) $registration['lease_public_key'],
                    'lease_issued_at' => $issuedAt,
                    'lease_expires_at' => $expiresAt,
                    'last_observed_at' => now()->greaterThan($issuedAt) ? now() : $issuedAt,
                    'cloud_base_url' => $registration['cloud_base_url'] ?? null,
                ],
            );

            return $tenant->fresh(['business']);
        });

        $databases->ensureDatabase($tenant);
        MigrateDatabase::dispatchSync($tenant);

        $rolesByCode = $rbac->ensureForTenant($tenant);
        $tenant->run(function () use ($registration, $request, $rolesByCode): void {
            $user = User::query()->find((int) $registration['user_id']);

            if ($user === null) {
                $user = new User;
                $user->setAttribute('id', (int) $registration['user_id']);
            }

            $user->fill([
                'name' => (string) $registration['user_name'],
                'email' => Str::lower((string) $registration['user_email']),
                'password' => $request->string('password')->toString(),
                'is_active' => true,
            ])->save();

            $role = $rolesByCode['owner'] ?? Role::query()->where('code', 'owner')->firstOrFail();
            $user->roles()->sync([$role->id]);
        });

        $settings->record($tenant);
        $inventory->ensureDefaults($tenant);
        $accounting->ensureDefaults($tenant);

        $leaseGuard->recordHighWaterMark(max(now()->getTimestamp(), $issuedAt->getTimestamp()));

        return response()->json([
            'data' => [
                'tenant_id' => (string) $tenant->id,
                'local_url' => (string) config('pharmacy.local_node.base_url'),
                'lease_expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    private function verifyWithCloud(array $registration): array
    {
        $url = rtrim(
            (string) config('pharmacy.local_node.licensing_url'),
            '/',
        ).'/api/v1/mobile/session/refresh';

        try {
            $response = Http::acceptJson()
                ->withToken((string) $registration['access_token'])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($url);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'registration' => 'The BusinessOS licensing server could not be reached for local activation.',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'registration' => 'BusinessOS licensing rejected this local activation.',
            ]);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'registration' => 'BusinessOS licensing returned an invalid activation response.',
            ]);
        }

        return $data;
    }
}