<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_same_email_can_login_to_correct_pharmacy_by_tenant_code(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $tenantB = Tenant::query()->create(['name' => 'B Pharmacy', 'slug' => 'b-pharmacy']);

        app(TrialProvisioner::class)->provision($tenantA);
        app(TrialProvisioner::class)->provision($tenantB);

        app(RbacProvisioner::class)->provisionOwner($tenantA, 'Owner A', 'owner@example.test', 'password-a');
        $userB = app(RbacProvisioner::class)->provisionOwner($tenantB, 'Owner B', 'owner@example.test', 'password-b');

        $this->post('/pharmacy/login', [
            'tenant' => 'b-pharmacy',
            'email' => 'owner@example.test',
            'password' => 'password-b',
        ])->assertRedirect('/pharmacy');

        $this->assertAuthenticatedAs($userB);
        $this->assertSame($tenantB->id, session('tenant_id'));

        $this->get('/pharmacy')
            ->assertOk()
            ->assertSee('B Pharmacy')
            ->assertSee('Owner B');
    }

    public function test_invalid_tenant_credentials_do_not_authenticate(): void
    {
        $this->from('/pharmacy/login')->post('/pharmacy/login', [
            'tenant' => 'missing',
            'email' => 'owner@example.test',
            'password' => 'wrong',
        ])
            ->assertRedirect('/pharmacy/login')
            ->assertSessionHasErrors('tenant');

        $this->assertGuest();
    }
}
