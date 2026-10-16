<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cashier_cannot_access_report_or_export_through_direct_urls(): void
    {
        $tenant = $this->createTenant(['slug' => 'report-access']);
        app(TrialProvisioner::class)->provision($tenant);
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $cashier = $tenant->run(function () use ($roles): User {
            $user = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@report-access.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $user->roles()->sync([$roles['cashier']->id]);

            return $user;
        });

        $this->onTenantDomain($tenant);
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/reports/export?type=returns')->assertForbidden();
    }
}
