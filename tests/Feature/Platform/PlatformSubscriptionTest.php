<?php

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSubscriptionTest extends TestCase
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

    public function test_platform_admin_can_assign_and_replace_current_subscription_settings(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul-pharmacy']);
        $plan = Plan::query()->create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1200,
            'billing_period' => 'monthly',
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->put("/platform/subscriptions/{$tenant->id}", [
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => '2026-09-28 00:00:00',
            'ends_at' => '2026-10-28 00:00:00',
            'auto_renew' => '1',
            'notes' => 'Paid manually',
        ])->assertRedirect();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->auto_renew);

        $this->put("/platform/subscriptions/{$tenant->id}", [
            'plan_id' => $plan->id,
            'status' => 'suspended',
            'starts_at' => '2026-09-28 00:00:00',
            'ends_at' => '2026-10-28 00:00:00',
            'auto_renew' => '0',
        ])->assertRedirect();

        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
    }

    public function test_inactive_plan_cannot_be_assigned(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Herat Pharmacy', 'slug' => 'herat-pharmacy']);
        $plan = Plan::query()->create([
            'name' => 'Legacy',
            'code' => 'LEGACY',
            'price' => 0,
            'billing_period' => 'yearly',
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $this->from("/platform/subscriptions/{$tenant->id}/edit")
            ->put("/platform/subscriptions/{$tenant->id}", [
                'plan_id' => $plan->id,
                'status' => 'pending',
                'auto_renew' => '0',
            ])
            ->assertRedirect("/platform/subscriptions/{$tenant->id}/edit")
            ->assertSessionHasErrors('plan_id');

        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $tenant->id]);
    }
}
