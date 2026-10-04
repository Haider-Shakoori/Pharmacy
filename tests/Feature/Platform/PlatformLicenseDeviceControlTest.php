<?php

namespace Tests\Feature\Platform;

use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformLicenseDeviceControlTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Subscription $subscription;

    private PlatformAdmin $admin;

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
            'name' => 'Support Controlled Pharmacy',
            'slug' => 'support-controlled',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Desktop Standard',
            'code' => 'DESKTOP-STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_windows_devices' => 1,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'features' => [],
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

        app(LicenseKeyService::class)->rotate($this->subscription);

        $this->admin = PlatformAdmin::query()->create([
            'name' => 'Support Operator',
            'email' => 'support-operator@example.test',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }

    public function test_support_can_issue_consume_and_replace_one_time_windows_key(): void
    {
        $issue = $this->actingAs($this->admin, 'platform')
            ->post(route('platform.licenses.windows-key', $this->subscription))
            ->assertRedirect()
            ->assertSessionHas('generated_license_key');

        $activationKey = $issue->getSession()->get('generated_license_key');

        $this->postJson('/api/v1/license/activate', [
            'license_key' => $activationKey,
            'device_id' => '12121212-1212-4212-8212-121212121212',
            'platform' => 'windows',
            'device_name' => 'Front Counter PC',
            'app_version' => '1.0.8',
            'os_version' => 'Windows 11',
        ])->assertOk();

        $activation = $this->subscription->license
            ->activations()
            ->where('device_id', '12121212-1212-4212-8212-121212121212')
            ->firstOrFail();

        $legacyHost = (string) config('pharmacy.deployment_host');

        $this->actingAs($this->admin, 'platform')
            ->get('https://'.$legacyHost.'/platform/licenses/'.$this->subscription->id)
            ->assertOk()
            ->assertSee('Front Counter PC')
            ->assertSee('One-time Windows keys');

        $replace = $this->actingAs($this->admin, 'platform')
            ->post(route('platform.licenses.devices.replace', [$this->subscription, $activation]))
            ->assertRedirect()
            ->assertSessionHas('generated_license_key');

        $this->assertNotSame(
            $activationKey,
            $replace->getSession()->get('generated_license_key'),
        );

        $this->assertNotNull($activation->fresh()->revoked_at);

        $this->assertDatabaseHas('license_support_actions', [
            'license_id' => $this->subscription->license->id,
            'action' => 'windows_device_replaced',
            'platform_admin_id' => $this->admin->id,
        ]);
    }
}
