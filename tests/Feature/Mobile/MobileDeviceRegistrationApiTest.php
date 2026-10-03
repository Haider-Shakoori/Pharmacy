<?php

namespace Tests\Feature\Mobile;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileDeviceRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pharmacy_user_can_register_android_device(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $tenant = $this->createTenant([
            'name' => 'Kabul Pharmacy',
            'slug' => 'kabul',
        ]);

        $tenant->run(function (): void {
            User::query()->create([
                'name' => 'Pharmacist',
                'email' => 'pharmacist@example.test',
                'password' => Hash::make('secret-password'),
                'is_active' => true,
            ]);
        });

        $plan = Plan::query()->create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 2,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'business_id' => $tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        $licenseKey = app(LicenseKeyService::class)->rotate($subscription);

        $response = $this->postJson('/api/v1/mobile/register', [
            'license_key' => $licenseKey,
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'device_name' => 'Galaxy POS',
            'device_model' => 'Samsung SM-S9080',
            'os_version' => '16',
            'app_version' => '0.1.0',
            'build_number' => '1',
            'email' => 'pharmacist@example.test',
            'password' => 'secret-password',
        ])->assertOk();

        $response
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonPath('data.user.email', 'pharmacist@example.test')
            ->assertJsonPath('data.tenant.cloud_base_url', 'https://kabul.'.config('pharmacy.tenant_domain'))
            ->assertJsonPath('data.device_id', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('data.offline_lease.public_key', config('pharmacy.license.signing_public_key'));

        $this->assertStringStartsWith('v1.', $response->json('data.access_token'));
        $this->assertStringStartsWith('v1.', $response->json('data.offline_lease.token'));

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'device_model' => 'Samsung SM-S9080',
            'os_version' => '16',
            'build_number' => '1',
            'revoked_at' => null,
        ]);
    }

    public function test_invalid_password_does_not_consume_device_slot(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $tenant = $this->createTenant(['slug' => 'wrong-password']);

        $tenant->run(function (): void {
            User::query()->create([
                'name' => 'Pharmacist',
                'email' => 'pharmacist@example.test',
                'password' => Hash::make('correct-password'),
                'is_active' => true,
            ]);
        });

        $plan = Plan::query()->create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'business_id' => $tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        $licenseKey = app(LicenseKeyService::class)->rotate($subscription);

        $this->postJson('/api/v1/mobile/register', [
            'license_key' => $licenseKey,
            'device_id' => '22222222-2222-4222-8222-222222222222',
            'email' => 'pharmacist@example.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('license_activations', 0);
    }
}
