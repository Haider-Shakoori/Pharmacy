<?php

namespace Tests\Feature\Pharmacy;

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

    public function test_same_email_can_login_to_correct_pharmacy_by_domain(): void
    {
        $tenantA = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        $tenantB = $this->createTenant(['name' => 'B Pharmacy', 'slug' => 'b-pharmacy']);

        app(TrialProvisioner::class)->provision($tenantA);
        app(TrialProvisioner::class)->provision($tenantB);
        app(RbacProvisioner::class)->provisionOwner($tenantA, 'Owner A', 'owner@example.test', 'password-a');
        $userB = app(RbacProvisioner::class)->provisionOwner($tenantB, 'Owner B', 'owner@example.test', 'password-b');

        $this->onTenantDomain($tenantB)
            ->post('/login', [
                'email' => 'owner@example.test',
                'password' => 'password-b',
            ])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($userB);

        $this->onTenantDomain($tenantB)
            ->get('/')
            ->assertOk()
            ->assertSee('B Pharmacy')
            ->assertSee('Owner B');
    }

    public function test_invalid_staff_credentials_do_not_authenticate(): void
    {
        $tenant = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);
        app(TrialProvisioner::class)->provision($tenant);
        app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@example.test', 'password-a');

        $this->onTenantDomain($tenant)
            ->from('/login')
            ->post('/login', [
                'email' => 'owner@example.test',
                'password' => 'wrong',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
