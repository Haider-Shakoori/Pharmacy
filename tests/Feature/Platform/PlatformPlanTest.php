<?php

namespace Tests\Feature\Platform;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPlanTest extends TestCase
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

    public function test_platform_admin_can_create_and_update_subscription_plan(): void
    {
        $this->post('/platform/plans', [
            'name' => 'Professional',
            'code' => 'pro',
            'description' => 'For established pharmacies',
            'price' => '3600',
            'currency' => 'afn',
            'billing_period' => 'quarterly',
            'max_users' => 10,
            'max_android_devices' => 5,
            'max_branches' => 2,
            'offline_grace_days' => 7,
            'features' => ['advanced_reports', 'multi_branch'],
            'is_active' => '1',
            'sort_order' => 20,
        ])->assertRedirect();

        $plan = Plan::query()->where('code', 'PRO')->firstOrFail();

        $this->assertSame('AFN', $plan->currency);
        $this->assertSame(BillingPeriod::Quarterly, $plan->billing_period);
        $this->assertTrue($plan->is_active);
        $this->assertSame(['advanced_reports', 'multi_branch'], $plan->features);

        $this->put("/platform/plans/{$plan->id}", [
            'name' => 'Professional Plus',
            'code' => 'PRO',
            'price' => '4200',
            'currency' => 'AFN',
            'billing_period' => 'quarterly',
            'max_users' => 15,
            'max_android_devices' => 8,
            'max_branches' => 3,
            'offline_grace_days' => 10,
            'features' => ['advanced_reports'],
            'is_active' => '1',
            'sort_order' => 20,
        ])->assertRedirect();

        $plan->refresh();

        $this->assertSame('Professional Plus', $plan->name);
        $this->assertSame('4200.00', $plan->price);
        $this->assertSame(10, $plan->offline_grace_days);
    }
}
