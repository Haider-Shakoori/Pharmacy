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

    public function test_pharmacy_dashboard_requires_authentication(): void
    {
        $this->get('/pharmacy')
            ->assertRedirect('/pharmacy/login');
    }
}
