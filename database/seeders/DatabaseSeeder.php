<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
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

        app(TenantContext::class)->run($tenant, function (): void {
            User::query()->firstOrCreate(
                ['email' => 'owner@demo.test'],
                [
                    'name' => 'Demo Pharmacy Owner',
                    'password' => 'password',
                ],
            );
        });
    }
}
