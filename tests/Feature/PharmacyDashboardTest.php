<?php

namespace Tests\Feature;

use Tests\TestCase;

class PharmacyDashboardTest extends TestCase
{
    public function test_root_redirects_to_pharmacy_dashboard(): void
    {
        $this->get('/')
            ->assertRedirect('/pharmacy');
    }

    public function test_pharmacy_dashboard_is_available(): void
    {
        $this->get('/pharmacy')
            ->assertOk()
            ->assertSee('BusinessOS Pharmacy')
            ->assertSee('pharmacy.businessos.af');
    }
}
