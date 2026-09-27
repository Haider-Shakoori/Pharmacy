<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DefaultRoleSeeder extends Seeder
{
    public function forTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->run($tenant, function (): void {
            $all = Permission::query()->pluck('id', 'key');

            $definitions = [
                'owner' => [
                    'name' => 'Owner',
                    'permissions' => $all->keys()->all(),
                ],
                'manager' => [
                    'name' => 'Manager',
                    'permissions' => $all->keys()->reject(fn ($key) => $key === 'roles.manage')->values()->all(),
                ],
                'pharmacist' => [
                    'name' => 'Pharmacist',
                    'permissions' => [
                        'dashboard.view', 'medicines.view', 'medicines.manage',
                        'inventory.view', 'inventory.manage', 'purchases.view',
                        'sales.create', 'returns.manage', 'reports.view',
                        'daily-closing.view', 'daily-closing.open', 'daily-closing.close',
                    ],
                ],
                'cashier' => [
                    'name' => 'Cashier',
                    'permissions' => [
                        'dashboard.view', 'medicines.view', 'inventory.view',
                        'sales.create', 'returns.manage',
                        'daily-closing.view', 'daily-closing.open', 'daily-closing.close',
                    ],
                ],
                'accountant' => [
                    'name' => 'Accountant',
                    'permissions' => [
                        'dashboard.view', 'reports.view',
                        'accounting.view', 'accounting.manage',
                        'daily-closing.view', 'daily-closing.approve',
                    ],
                ],
            ];

            foreach ($definitions as $slug => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $definition['name'], 'is_system' => true],
                );

                $role->permissions()->sync(
                    $all->only($definition['permissions'])->values(),
                );
            }
        });
    }

    public function run(): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => $this->forTenant($tenant));
    }
}
