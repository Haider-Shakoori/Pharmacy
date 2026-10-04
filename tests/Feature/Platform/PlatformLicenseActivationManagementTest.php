<?php

namespace Tests\Feature\Platform;

use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformLicenseActivationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Subscription $subscription;

    private string $licenseKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $this->tenant = $this->createTenant([
            'name' => 'Support Managed Pharmacy',
            'slug' => 'support-managed',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Desktop',
            'code' => 'DESKTOP',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_windows_devices' => 3,
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

    public function test_platform_can_view_device_and_signed_in_staff_session(): void
    {
        $admin = $this->platformAdmin();
        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@example.test',
            'secret-password',
        );

        $deviceId = '80000000-0000-4000-8000-000000000001';
        $activation = $this->activateWindows($deviceId);

        $this->withToken($activation['lease_token'])
            ->withHeader('User-Agent', 'Darmaltoon Windows 1.0.8')
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $deviceId,
                'email' => 'owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk();

        $this->actingAs($admin, 'platform')
            ->get($this->legacyPlatformUrl("/licenses/{$this->subscription->id}"))
            ->assertOk()
            ->assertSee('Activated devices')
            ->assertSee('Front Counter')
            ->assertSee('owner@example.test')
            ->assertSee('Consumed');
    }

    public function test_support_can_force_sign_out_a_specific_desktop_session(): void
    {
        $admin = $this->platformAdmin();
        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@example.test',
            'secret-password',
        );

        $deviceId = '81111111-1111-4111-8111-111111111111';
        $activation = $this->activateWindows($deviceId);

        $login = $this->withToken($activation['lease_token'])
            ->withHeader('User-Agent', 'Darmaltoon Windows 1.0.8')
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $deviceId,
                'email' => 'owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk();

        $accessToken = $login->json('data.access_token');

        $this->actingAs($admin, 'platform')
            ->post($this->legacyPlatformUrl(
                "/licenses/{$this->subscription->id}/activations/{$activation['activation_id']}/force-sign-out"
            ), [
                'reason' => 'Customer requested emergency sign-out.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('license_support_actions', [
            'license_id' => $this->subscription->license()->value('id'),
            'activation_id' => $activation['activation_id'],
            'platform_admin_id' => $admin->id,
            'action' => 'force_sign_out',
        ]);

        $this->withToken($accessToken)
            ->getJson('/api/v1/desktop/sync/status')
            ->assertUnauthorized();
    }

    public function test_support_reset_revokes_old_device_and_issues_a_brand_new_one_time_key(): void
    {
        $admin = $this->platformAdmin();
        $oldDevice = '82222222-2222-4222-8222-222222222222';
        $activation = $this->activateWindows($oldDevice);

        $response = $this->actingAs($admin, 'platform')
            ->post($this->legacyPlatformUrl(
                "/licenses/{$this->subscription->id}/activations/{$activation['activation_id']}/reset-device"
            ), [
                'reason' => 'Windows was reinstalled after a PC failure.',
            ])
            ->assertRedirect()
            ->assertSessionHas('generated_license_key');

        $newKey = (string) $response->getSession()->get('generated_license_key');

        $this->assertNotSame($this->licenseKey, $newKey);

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '83333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_key');

        $newActivation = $this->postJson('/api/v1/license/activate', [
            'license_key' => $newKey,
            'device_id' => '84444444-4444-4444-8444-444444444444',
            'platform' => 'windows',
            'device_name' => 'Replacement PC',
        ])->assertOk();

        $this->assertNotEmpty($newActivation->json('data.lease_token'));

        $this->assertDatabaseHas('license_activations', [
            'id' => $activation['activation_id'],
            'device_id' => $oldDevice,
        ]);

        $this->assertDatabaseHas('license_support_actions', [
            'license_id' => $this->subscription->license()->value('id'),
            'activation_id' => $activation['activation_id'],
            'platform_admin_id' => $admin->id,
            'action' => 'reset_and_reassign',
        ]);
    }

    private function activateWindows(string $deviceId): array
    {
        $response = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => $deviceId,
            'platform' => 'windows',
            'device_name' => 'Front Counter',
            'app_version' => '1.0.8',
            'os_version' => 'Windows 11',
        ])->assertOk();

        return [
            'activation_id' => $response->json('data.activation_id'),
            'lease_token' => $response->json('data.lease_token'),
        ];
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::query()->create([
            'name' => 'Support Operator',
            'email' => 'support@example.test',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }

    private function legacyPlatformUrl(string $path): string
    {
        return 'https://'.config('pharmacy.deployment_host').'/platform'.$path;
    }
}
