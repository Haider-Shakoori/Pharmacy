<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Commercial\DarmaltoonLaunchPricing as Pricing;
use Illuminate\Database\Seeder;

class DarmaltoonLaunchPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Explicit opt-in. Never overwrite established plans or existing customer terms.
        // Activate after a reviewed production readiness/fulfilment check.
        Plan::query()->firstOrCreate(['code' => Pricing::PLAN_CODE], [
            'name' => 'Darmaltoon Standard Annual',
            'description' => 'One pharmacy, one Windows PC and two Android POS devices. First 10 verified payments receive first-year launch pricing.',
            'price' => Pricing::REGULAR_FIRST_YEAR,
            'currency' => Pricing::CURRENCY,
            'billing_period' => 'yearly',
            'max_users' => null,
            'max_android_devices' => 2,
            'max_windows_devices' => 1,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'features' => [],
            'is_active' => false,
            'sort_order' => 10,
        ]);
    }
}
