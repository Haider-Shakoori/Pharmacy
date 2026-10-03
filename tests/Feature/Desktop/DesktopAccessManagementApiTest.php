<?php

namespace Tests\Feature\Desktop;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopAccessManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $accessToken;

    private string $deviceId = '88888888-8888-4888-8888-888888888888';

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(
                sodium_crypto_sign_secretkey($keyPair),
            ),
            'pharmacy.license.signing_public_key' => base64_encode(
                sodium_crypto_sign_publickey($keyPair),
            ),
        ]);

        $this->tenant = $this->createTenant([
            'name' => 'Desktop Access Pharmacy',
            'slug' => 'desktop-access-pharmacy',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Desktop Access',
            'code' => 'DESKTOP_ACCESS',
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

        $subscription = Subscription::query()->create([
            'business_id' => $this->tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        $licenseKey = app(LicenseKeyService::class)->rotate($subscription);

        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Desktop Owner',
            'desktop-access-owner@example.test',
            'secret-password',
        );

        $activation = $this->postJson('/api/v1/license/activate', [
            'license_key' => $licenseKey,
            'device_id' => $this->deviceId,
            'platform' => 'windows',
            'device_name' => 'Main Pharmacy Server',
        ])->assertOk();

        $login = $this->withToken($activation->json('data.lease_token'))
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $this->deviceId,
                'email' => 'desktop-access-owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk();

        $this->accessToken = (string) $login->json('data.access_token');
    }

    public function test_owner_can_load_desktop_access_snapshot(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/desktop/access')
            ->assertOk()
            ->assertJsonPath('data.users.0.email', 'desktop-access-owner@example.test')
            ->assertJsonStructure([
                'data' => [
                    'users' => [['id', 'name', 'email', 'is_active', 'roles']],
                    'roles',
                    'permissions',
                ],
            ]);
    }

    public function test_owner_can_create_and_update_pharmacy_user_from_desktop(): void
    {
        $roleId = $this->tenant->run(
            fn (): int => (int) Role::query()->where('code', 'owner')->value('id'),
        );

        $created = $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/access/users', [
                'name' => 'Desktop Cashier',
                'email' => 'desktop-cashier@example.test',
                'password' => 'cashier-password',
                'is_active' => true,
                'role_ids' => [$roleId],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pharmacy user created.');

        $userId = collect($created->json('data.users'))
            ->firstWhere('email', 'desktop-cashier@example.test')['id'];

        $this->withToken($this->accessToken)
            ->putJson("/api/v1/desktop/access/users/{$userId}", [
                'name' => 'Desktop Cashier Updated',
                'email' => 'desktop-cashier@example.test',
                'password' => null,
                'is_active' => true,
                'role_ids' => [$roleId],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Pharmacy user updated.');

        $this->tenant->run(function (): void {
            $this->assertSame(
                'Desktop Cashier Updated',
                User::query()->where('email', 'desktop-cashier@example.test')->value('name'),
            );
        });
    }

    public function test_owner_can_create_and_update_custom_role_from_desktop(): void
    {
        $permissionIds = $this->tenant->run(
            fn (): array => Permission::query()->orderBy('id')->limit(2)->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        $created = $this->withToken($this->accessToken)
            ->postJson('/api/v1/desktop/access/roles', [
                'name' => 'Counter Staff',
                'code' => 'counter_staff',
                'permission_ids' => $permissionIds,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Custom role created.');

        $roleId = collect($created->json('data.roles'))
            ->firstWhere('code', 'counter_staff')['id'];

        $allPermissionIds = $this->tenant->run(
            fn (): array => Permission::query()->orderBy('id')->limit(3)->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        $this->withToken($this->accessToken)
            ->putJson("/api/v1/desktop/access/roles/{$roleId}", [
                'permission_ids' => $allPermissionIds,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Role permissions updated.');
    }
}
