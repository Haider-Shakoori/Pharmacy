<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locale_is_stored_in_tenant_session(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);

        $this->onTenantDomain($tenant)
            ->from('/')
            ->get('/locale/fa')
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'fa');
    }

    public function test_unsupported_locale_is_rejected_on_tenant_domain(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);

        $this->onTenantDomain($tenant)
            ->get('/locale/de')
            ->assertNotFound();
    }
}
