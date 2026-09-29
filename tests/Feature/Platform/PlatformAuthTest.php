<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_platform_dashboard_requires_platform_authentication(): void
    {
        $this->get('/platform')
            ->assertRedirect('/platform/login');
    }

    public function test_active_platform_admin_can_login_and_logout(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->post('/platform/login', [
            'email' => 'ADMIN@example.test',
            'password' => 'secret-password',
        ])->assertRedirect('/platform');

        $this->assertAuthenticatedAs($admin, 'platform');

        $this->post('/platform/logout')
            ->assertRedirect('/platform/login');

        $this->assertGuest('platform');
    }

    public function test_inactive_platform_admin_cannot_login(): void
    {
        PlatformAdmin::query()->create([
            'name' => 'Disabled Admin',
            'email' => 'disabled@example.test',
            'password' => 'secret-password',
            'is_active' => false,
        ]);

        $this->from('/platform/login')->post('/platform/login', [
            'email' => 'disabled@example.test',
            'password' => 'secret-password',
        ])
            ->assertRedirect('/platform/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_platform_password_page_requires_platform_authentication(): void
    {
        $this->get('/platform/account/password')
            ->assertRedirect('/platform/login');
    }

    public function test_platform_admin_can_change_own_password(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'old-password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'platform')
            ->get('/platform/account/password')
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('admin@example.test');

        $this->actingAs($admin, 'platform')
            ->put('/platform/account/password', [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('platform.account.password.edit'))
            ->assertSessionHas('success');

        $admin->refresh();

        $this->assertTrue(Hash::check('new-password-123', $admin->password));
        $this->assertFalse(Hash::check('old-password', $admin->password));
        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_platform_password_change_rejects_incorrect_current_password(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'old-password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'platform')
            ->from('/platform/account/password')
            ->put('/platform/account/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect('/platform/account/password')
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $admin->fresh()->password));
    }
}
