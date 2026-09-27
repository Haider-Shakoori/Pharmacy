<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
