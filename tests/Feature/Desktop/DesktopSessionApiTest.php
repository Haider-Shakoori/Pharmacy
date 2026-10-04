<?php

namespace Tests\Feature\Desktop;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\SignedTokenVerifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DesktopSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Subscription $subscription;

    private string $licenseKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $this->tenant = $this->createTenant([
            'name' => 'Desktop Pharmacy',
            'slug' => 'desktop-pharmacy',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_windows_devices' => 2,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'features' => ['advanced_reports'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->subscription = Subscription::query()->create([
            'business_id' => $this->tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        $this->licenseKey = app(LicenseKeyService::class)->rotate($this->subscription);
    }

    public function test_owner_can_login_with_windows_lease_and_receive_signed_rbac_snapshot(): void
    {
        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@example.test',
            'secret-password',
        );

        $deviceId = '11111111-1111-4111-8111-111111111111';
        $lease = $this->activateWindows($deviceId);

        $response = $this->withToken($lease)
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $deviceId,
                'email' => 'owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'owner@example.test')
            ->assertJsonPath('data.user.roles.0', 'owner')
            ->assertJsonPath('data.subscription_health', 'healthy');

        $this->assertContains('users.manage', $response->json('data.user.permissions'));
        $this->assertContains('pos.sell', $response->json('data.user.permissions'));

        $payload = app(SignedTokenVerifier::class)->verify(
            $response->json('data.access_token'),
            purpose: 'desktop_access',
        );

        $this->assertSame($deviceId, $payload['device_id']);
        $this->assertSame((string) $this->tenant->id, (string) $payload['tenant_id']);
        $this->assertContains('owner', $payload['roles']);
        $this->assertContains('daily_closing.reopen', $payload['permissions']);
        $this->assertSame(1, $payload['session_version']);

        $this->assertDatabaseHas('license_activations', [
            'device_id' => $deviceId,
            'current_user_email' => 'owner@example.test',
            'current_user_name' => 'Owner',
            'session_version' => 1,
        ]);
    }

    public function test_invalid_password_is_rejected_without_changing_activation(): void
    {
        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@example.test',
            'correct-password',
        );

        $deviceId = '22222222-2222-4222-8222-222222222222';
        $lease = $this->activateWindows($deviceId);

        $this->withToken($lease)
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $deviceId,
                'email' => 'owner@example.test',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('license_activations', [
            'device_id' => $deviceId,
            'platform' => 'windows',
            'revoked_at' => null,
        ]);
    }

    public function test_desktop_session_is_bound_to_the_activated_windows_installation(): void
    {
        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@example.test',
            'secret-password',
        );

        $lease = $this->activateWindows('33333333-3333-4333-8333-333333333333');

        $this->withToken($lease)
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => '44444444-4444-4444-8444-444444444444',
                'email' => 'owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertUnauthorized();
    }

    public function test_online_session_refresh_picks_up_changed_roles_and_permissions(): void
    {
        $roles = app(RbacProvisioner::class)->ensureForTenant($this->tenant);

        $user = app(TenantContext::class)->run($this->tenant, function () use ($roles): User {
            $user = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@example.test',
                'password' => Hash::make('secret-password'),
                'is_active' => true,
            ]);
            $user->roles()->sync([$roles['cashier']->id]);

            return $user;
        });

        $deviceId = '55555555-5555-4555-8555-555555555555';
        $lease = $this->activateWindows($deviceId);

        $login = $this->withToken($lease)
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $deviceId,
                'email' => 'cashier@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk();

        $this->assertContains('pos.sell', $login->json('data.user.permissions'));
        $this->assertNotContains('inventory.adjust', $login->json('data.user.permissions'));

        app(TenantContext::class)->run($this->tenant, function () use ($user, $roles): void {
            $user->roles()->sync([$roles['inventory']->id]);
        });

        $refresh = $this->withToken($login->json('data.access_token'))
            ->postJson('/api/v1/desktop/session/refresh', [
                'device_id' => $deviceId,
            ])
            ->assertOk();

        $this->assertContains('inventory.adjust', $refresh->json('data.user.permissions'));
        $this->assertNotContains('pos.sell', $refresh->json('data.user.permissions'));
    }

    private function activateWindows(string $deviceId): string
    {
        return $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => $deviceId,
            'platform' => 'windows',
            'device_name' => 'Pharmacy Counter',
        ])
            ->assertOk()
            ->json('data.lease_token');
    }
}
