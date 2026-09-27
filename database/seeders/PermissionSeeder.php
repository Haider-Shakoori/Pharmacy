<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['key' => 'dashboard.view', 'label' => 'View dashboard', 'group' => 'Dashboard'],
        ['key' => 'users.manage', 'label' => 'Manage users', 'group' => 'Administration'],
        ['key' => 'roles.manage', 'label' => 'Manage roles and permissions', 'group' => 'Administration'],
        ['key' => 'medicines.view', 'label' => 'View medicines', 'group' => 'Medicines'],
        ['key' => 'medicines.manage', 'label' => 'Manage medicines', 'group' => 'Medicines'],
        ['key' => 'inventory.view', 'label' => 'View inventory', 'group' => 'Inventory'],
        ['key' => 'inventory.manage', 'label' => 'Manage inventory', 'group' => 'Inventory'],
        ['key' => 'purchases.view', 'label' => 'View purchases', 'group' => 'Purchasing'],
        ['key' => 'purchases.manage', 'label' => 'Manage purchases', 'group' => 'Purchasing'],
        ['key' => 'sales.create', 'label' => 'Create sales', 'group' => 'Sales'],
        ['key' => 'sales.void', 'label' => 'Void sales', 'group' => 'Sales'],
        ['key' => 'returns.manage', 'label' => 'Manage returns', 'group' => 'Sales'],
        ['key' => 'reports.view', 'label' => 'View reports', 'group' => 'Reports'],
        ['key' => 'accounting.view', 'label' => 'View accounting', 'group' => 'Accounting'],
        ['key' => 'accounting.manage', 'label' => 'Manage accounting', 'group' => 'Accounting'],
        ['key' => 'daily-closing.view', 'label' => 'View daily closings', 'group' => 'Daily Closing'],
        ['key' => 'daily-closing.open', 'label' => 'Open business day or shift', 'group' => 'Daily Closing'],
        ['key' => 'daily-closing.close', 'label' => 'Close business day or shift', 'group' => 'Daily Closing'],
        ['key' => 'daily-closing.approve', 'label' => 'Approve daily closing', 'group' => 'Daily Closing'],
        ['key' => 'daily-closing.reopen', 'label' => 'Reopen a closed day', 'group' => 'Daily Closing'],
        ['key' => 'settings.manage', 'label' => 'Manage pharmacy settings', 'group' => 'Administration'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['key' => $permission['key']],
                $permission,
            );
        }
    }
}
