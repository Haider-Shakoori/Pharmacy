<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('pharmacy.platform.bootstrap_admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            return;
        }

        PlatformAdmin::query()->firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'is_active' => true,
            ],
        );
    }
}
