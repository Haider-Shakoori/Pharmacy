<?php

namespace Tests\Feature\Licensing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
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

    public function test_windows_device_has_separate_limit_from_android(): void
    {
        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'platform' => 'android',
        ])->assertOk();

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $this->plainTextKey,
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
            'device_name' => 'Pharmacy Counter PC',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.max_windows_devices', 1);

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '33333333-3333-4333-8333-333333333333',
            'platform' => 'windows',
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
}
