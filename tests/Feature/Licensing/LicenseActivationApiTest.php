<?php

namespace Tests\Feature\Licensing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\SignedTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseActivationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Subscription $subscription;

    private string $plainTextKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $this->tenant = $this->createTenant([
            'name' => 'Kabul Pharmacy',
            'slug' => 'kabul',
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
            'features' => ['advanced_reports', 'api_access'],
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

        $this->plainTextKey = app(LicenseKeyService::class)->rotate($this->subscription);
    }

    public function test_android_device_can_activate_and_receive_signed_offline_lease(): void
    {
        $response = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'device_name' => 'Samsung POS',
            'app_version' => '1.0.0',
        ])->assertOk();

        $response->assertJsonPath('data.tenant.id', $this->tenant->id)
            ->assertJsonPath('data.plan.code', 'STANDARD');

        $this->assertStringStartsWith('v1.', $response->json('data.lease_token'));

        $payload = app(SignedTokenVerifier::class)->verify(
            $response->json('data.lease_token'),
        );

        $this->assertSame(1, $payload['entitlement_version']);
        $this->assertSame('android', $payload['platform']);
        $this->assertSame(['advanced_reports', 'api_access'], $payload['entitlements']);
        $this->assertSame($payload['expires_at'], $payload['offline_valid_until']);
        $this->assertArrayHasKey('server_time', $payload);
        $this->assertArrayHasKey('subscription_expires_at', $payload);

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'revoked_at' => null,
        ]);
    }

    public function test_plan_device_limit_blocks_second_android_device(): void
    {
        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
        ])->assertOk();

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '22222222-2222-4222-8222-222222222222',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_windows_device_has_separate_limit_and_records_desktop_metadata(): void
    {
        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'platform' => 'android',
        ])->assertOk();

        $response = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
            'device_name' => 'Pharmacy Counter PC',
            'app_version' => '1.0.0',
            'device_model' => 'Dell OptiPlex',
            'os_version' => 'Windows 11 24H2',
            'build_number' => '26100',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.max_windows_devices', 1);

        $payload = app(SignedTokenVerifier::class)->verify(
            $response->json('data.lease_token'),
        );

        $this->assertSame('windows', $payload['platform']);
        $this->assertSame(['advanced_reports', 'api_access'], $payload['entitlements']);

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
            'device_name' => 'Pharmacy Counter PC',
            'app_version' => '1.0.0',
            'device_model' => 'Dell OptiPlex',
            'os_version' => 'Windows 11 24H2',
            'build_number' => '26100',
            'revoked_at' => null,
        ]);

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '44444444-4444-4444-8444-444444444444',
            'platform' => 'windows',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_invalid_or_inactive_subscription_cannot_activate(): void
    {
        $this->subscription->update(['status' => 'suspended']);

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_key');
    }

    public function test_windows_activation_can_refresh_lease_without_resending_license_key(): void
    {
        $activation = $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '55555555-5555-4555-8555-555555555555',
            'platform' => 'windows',
            'device_name' => 'Front Counter',
            'app_version' => '1.0.0',
        ])->assertOk();

        $leaseToken = $activation->json('data.lease_token');

        $refresh = $this->withToken($leaseToken)
            ->postJson('/api/v1/desktop/license/refresh', [
                'device_id' => '55555555-5555-4555-8555-555555555555',
                'device_name' => 'Front Counter',
                'app_version' => '1.0.1',
                'os_version' => 'Windows 11',
                'build_number' => '26100',
            ])
            ->assertOk()
            ->assertJsonPath('data.subscription_health', 'healthy')
            ->assertJsonPath('data.plan.code', 'STANDARD');

        $refreshedPayload = app(SignedTokenVerifier::class)->verify(
            $refresh->json('data.lease_token'),
            purpose: 'offline_lease',
        );

        $this->assertSame('windows', $refreshedPayload['platform']);
        $this->assertSame(
            ['advanced_reports', 'api_access'],
            $refreshedPayload['entitlements'],
        );

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '55555555-5555-4555-8555-555555555555',
            'app_version' => '1.0.1',
            'os_version' => 'Windows 11',
            'build_number' => '26100',
            'revoked_at' => null,
        ]);

        $this->withToken($leaseToken)
            ->postJson('/api/v1/desktop/license/refresh', [
                'device_id' => '66666666-6666-4666-8666-666666666666',
            ])
            ->assertUnauthorized();
    }

}
