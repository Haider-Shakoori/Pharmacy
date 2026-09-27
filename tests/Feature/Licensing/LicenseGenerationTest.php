<?php

namespace Tests\Feature\Licensing;

use App\Models\License;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'platform');
    }

    public function test_first_subscription_assignment_generates_one_time_plaintext_license_key(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
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

        $response = $this->put("/platform/subscriptions/{$tenant->id}", [
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'auto_renew' => '0',
        ]);

        $response->assertRedirect();

        $plainText = session('generated_license_key');

        $this->assertIsString($plainText);
        $this->assertStringStartsWith('PHM-', $plainText);

        $license = License::query()->firstOrFail();

        $this->assertSame(hash('sha256', $plainText), $license->key_hash);
        $this->assertStringNotContainsString($plainText, json_encode($license->toArray()));
    }

    public function test_regeneration_rotates_hash_and_increments_version(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        $plan = Plan::query()->create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->put("/platform/subscriptions/{$tenant->id}", [
            'plan_id' => $plan->id,
            'status' => 'active',
            'auto_renew' => '0',
        ]);

        $subscription = $tenant->subscription()->firstOrFail();
        $license = $subscription->license()->firstOrFail();
        $oldHash = $license->key_hash;

        $this->post("/platform/licenses/{$subscription->id}/regenerate")
            ->assertRedirect()
            ->assertSessionHas('generated_license_key');

        $license->refresh();

        $this->assertNotSame($oldHash, $license->key_hash);
        $this->assertSame(2, $license->version);
    }
}
