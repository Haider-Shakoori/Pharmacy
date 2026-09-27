<?php

namespace Database\Seeders;

use App\Models\Role;
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
        $this->call([
            PlatformAdminSeeder::class,
            PermissionSeeder::class,
        ]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo-pharmacy'],
            [
                'name' => 'Demo Pharmacy',
                'timezone' => 'Asia/Kabul',
                'currency' => 'AFN',
                'locale' => 'en',
            ],
        );

        (new DefaultRoleSeeder)->forTenant($tenant);

        app(TenantContext::class)->run($tenant, function (): void {
            $user = User::query()->firstOrCreate(
                ['email' => 'owner@demo.test'],
                [
                    'name' => 'Demo Pharmacy Owner',
                    'password' => 'password',
                    'is_active' => true,
                    'preferred_locale' => 'en',
                ],
            );

            $owner = Role::query()->where('slug', 'owner')->firstOrFail();
            $user->roles()->syncWithoutDetaching([$owner->id]);
        });
    }
}
