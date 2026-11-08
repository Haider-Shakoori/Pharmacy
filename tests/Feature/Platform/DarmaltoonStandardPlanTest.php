<?php

namespace Tests\Feature\Platform;

use App\Models\Plan;
use App\Support\Commercial\DarmaltoonStandardOffer as Standard;
use Database\Seeders\DarmaltoonStandardPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarmaltoonStandardPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_seed_creates_inactive_one_pharmacy_one_windows_two_android_annual_offer(): void
    {
        $this->seed(DarmaltoonStandardPlanSeeder::class);

        $plan = Plan::query()->where('code', Standard::PLAN_CODE)->sole();

        $this->assertSame('5000.00', $plan->price);
        $this->assertSame('AFN', $plan->currency);
        $this->assertSame('yearly', $plan->billing_period->value);
        $this->assertSame(1, $plan->max_branches);
        $this->assertSame(1, $plan->max_windows_devices);
        $this->assertSame(2, $plan->max_android_devices);
        $this->assertFalse($plan->is_active);
        $this->assertSame('3000.00', Standard::RENEWAL_AFN);
    }

    public function test_seeding_again_never_overwrites_existing_customer_plan_or_launch_status(): void
    {
        $this->seed(DarmaltoonStandardPlanSeeder::class);

        $plan = Plan::query()->where('code', Standard::PLAN_CODE)->sole();
        $plan->update([
            'price' => 4200,
            'max_android_devices' => 1,
            'is_active' => true,
        ]);

        $this->seed(DarmaltoonStandardPlanSeeder::class);

        $plan->refresh();

        $this->assertSame('4200.00', $plan->price);
        $this->assertSame(1, $plan->max_android_devices);
        $this->assertTrue($plan->is_active);
        $this->assertSame(1, Plan::query()->where('code', Standard::PLAN_CODE)->count());
    }
}
