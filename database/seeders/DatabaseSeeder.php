<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo-pharmacy'],
            [
                'name' => 'Demo Pharmacy',
                'timezone' => 'Asia/Kabul',
                'currency' => 'AFN',
                'locale' => 'en',
            ],
        );

        app(TrialProvisioner::class)->provision($tenant);

        if ($tenant->users()->count() === 0) {
            app(RbacProvisioner::class)->provisionOwner(
                $tenant,
                'Demo Pharmacy Owner',
                'owner@demo.test',
                'password',
            );
        } else {
            app(RbacProvisioner::class)->ensureForTenant($tenant);
        }
    }
}
