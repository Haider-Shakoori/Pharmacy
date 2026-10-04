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
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_windows_devices' => 1,
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

    public function test_support_reassignment_revokes_windows_only_and_issues_a_new_one_time_key(): void
    {
        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'platform' => 'android',
            'device_name' => 'Android POS',
        ])->assertOk();

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '22222222-2222-4222-8222-222222222222',
            'platform' => 'windows',
            'device_name' => 'Old Windows PC',
        ])->assertOk();

        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin, 'platform')
            ->post(route('platform.licenses.windows.reassign', $this->subscription))
            ->assertRedirect()
            ->assertSessionHas('generated_windows_activation_key');

        $newWindowsKey = (string) $response->getSession()->get('generated_windows_activation_key');

        $this->assertNotSame('', $newWindowsKey);
        $this->assertNotSame($this->licenseKey, $newWindowsKey);

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'platform' => 'android',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseMissing('license_activations', [
            'device_id' => '22222222-2222-4222-8222-222222222222',
            'platform' => 'windows',
            'revoked_at' => null,
        ]);

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_key');

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $newWindowsKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
            'device_name' => 'Replacement PC',
        ])->assertOk();

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $newWindowsKey,
            'device_id' => '44444444-4444-4444-8444-444444444444',
            'platform' => 'windows',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_key');

        $this->assertDatabaseHas('license_support_actions', [
            'license_id' => $this->subscription->license->id,
            'platform_admin_id' => $admin->id,
            'action' => 'windows_reassigned',
        ]);
    }

    public function test_force_sign_out_invalidates_the_existing_desktop_access_token(): void
    {
        app(RbacProvisioner::class)->provisionOwner(
            $this->tenant,
            'Owner',
            'owner@example.test',
            'secret-password',
        );

        $deviceId = '55555555-5555-4555-8555-555555555555';

        $activation = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->licenseKey,
            'device_id' => $deviceId,
            'platform' => 'windows',
            'device_name' => 'Front Counter',
        ])->assertOk();

        $lease = $activation->json('data.lease_token');

        $login = $this->withToken($lease)
            ->postJson('/api/v1/desktop/session/login', [
                'device_id' => $deviceId,
                'email' => 'owner@example.test',
                'password' => 'secret-password',
            ])
            ->assertOk();

        $accessToken = $login->json('data.access_token');
        $activationId = $activation->json('data.activation_id');
        $admin = $this->platformAdmin();

        $this->actingAs($admin, 'platform')
            ->post(route('platform.licenses.activations.force-sign-out', [
                $this->subscription,
                $activationId,
            ]))
            ->assertRedirect();

        $this->withToken($accessToken)
            ->postJson('/api/v1/desktop/session/refresh', [
                'device_id' => $deviceId,
            ])
            ->assertUnauthorized();

        $this->assertDatabaseHas('license_activations', [
            'id' => $activationId,
            'current_user_email' => null,
            'session_version' => 2,
        ]);

        $this->assertDatabaseHas('license_support_actions', [
            'license_activation_id' => $activationId,
            'platform_admin_id' => $admin->id,
            'action' => 'force_sign_out',
        ]);
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::query()->create([
            'name' => 'Support Admin',
            'email' => 'support@example.test',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }
}
