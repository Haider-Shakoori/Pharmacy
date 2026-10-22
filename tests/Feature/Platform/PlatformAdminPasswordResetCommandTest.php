<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdminPasswordResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_and_stores_a_new_platform_admin_password(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'old-password',
            'is_active' => false,
        ]);

        $oldHash = $admin->password;

        $this->artisan('platform:admin:reset-password', [
            'email' => 'ADMIN@EXAMPLE.TEST',
            '--length' => 24,
        ])->assertSuccessful();

        $admin->refresh();

        $this->assertTrue($admin->is_active);
        $this->assertNotSame($oldHash, $admin->password);
        $this->assertFalse(Hash::check('old-password', $admin->password));
    }

    public function test_command_rejects_unsafe_password_lengths(): void
    {
        PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'old-password',
            'is_active' => true,
        ]);

        $this->artisan('platform:admin:reset-password', [
            'email' => 'admin@example.test',
            '--length' => 8,
        ])->assertFailed();
    }
}
