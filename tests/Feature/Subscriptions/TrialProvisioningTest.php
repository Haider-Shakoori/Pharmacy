<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\SubscriptionHealth;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialProvisioningTest extends TestCase
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

    public function test_ready_pharmacy_requires_explicit_one_time_trial_start(): void
    {
        $this->post('/platform/tenants', [
            'name' => 'Kabul Trial Pharmacy',
            'slug' => 'kabul-trial',
            'contact_person' => 'Trial Owner',
            'phone_whatsapp' => '+93700123456',
            'location' => 'Kabul, Afghanistan',
            'timezone' => 'Asia/Kabul',
            'currency' => 'AFN',
            'locale' => 'fa',
            'owner_name' => 'Trial Owner',
            'owner_email' => 'owner@trial.test',
            'owner_password' => 'password123',
        ])->assertRedirect();

        $business = Business::query()->where('slug', 'kabul-trial')->firstOrFail();
        $tenant = Tenant::query()->findOrFail($business->tenant_id);

        $this->assertSame('application_ready', $tenant->provisioning_status);
        $this->assertNull($tenant->subscription);
        $this->assertNull($business->trial_used_at);

        $response = $this->post("/platform/tenants/{$tenant->id}/trial/start")
            ->assertRedirect()
            ->assertSessionHas('generated_license_key');

        $tenant->refresh()->load('subscription.license');
        $business->refresh();

        $this->assertSame(SubscriptionStatus::Trial, $tenant->subscription->status);
        $this->assertTrue($tenant->subscription->trial_ends_at->between(now()->addDays(6)->addHours(23), now()->addDays(7)->addMinute()));
        $this->assertNotNull($tenant->subscription->license);
        $this->assertNotNull($business->trial_used_at);
        $this->assertSame(SubscriptionHealth::Trial, app(SubscriptionHealthService::class)->forTenant($tenant));

        $this->post("/platform/tenants/{$tenant->id}/trial/start")
            ->assertRedirect()
            ->assertSessionHasErrors('trial');

    }
}
