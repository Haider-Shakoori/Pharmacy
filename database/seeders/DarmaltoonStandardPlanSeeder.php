<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Commercial\DarmaltoonStandardOffer;
use Illuminate\Database\Seeder;

/**
 * Explicit operator action only, NOT in DatabaseSeeder.
 * Never updates an existing plan's price, limits or active state.
 */
class DarmaltoonStandardPlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->firstOrCreate(
            ['code' => DarmaltoonStandardOffer::PLAN_CODE],
            DarmaltoonStandardOffer::planAttributes(),
        );
    }
}
