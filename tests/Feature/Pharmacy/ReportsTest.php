<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\DailyClosing\BusinessDateResolver;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_tenant_scoped_and_exportable(): void
    {
        $tenant = $this->createTenant(['slug' => 'reports-test']);
        app(TrialProvisioner::class)->provision($tenant);

        $tenant->run(function () use ($tenant): void {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@reports.test', 'password' => 'password', 'is_active' => true]);
            $owner->roles()->sync([$roles['owner']->id]);
            $branch = Branch::query()->create(['code' => 'RPT', 'name' => 'Reports', 'is_active' => true]);
            $location = StockLocation::query()->create(['branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main', 'is_active' => true]);

            Sale::query()->create([
                'sale_number' => 'POS-RPT-001',
                'stock_location_id' => $location->id,
                'business_date' => app(BusinessDateResolver::class)->resolve($tenant),
                'status' => 'completed',
                'currency' => 'AFN',
                'subtotal' => 100,
                'discount_total' => 10,
                'tax_total' => 0,
                'grand_total' => 90,
                'paid_total' => 90,
                'due_total' => 0,
                'change_total' => 0,
                'payment_status' => 'paid',
                'created_by' => $owner->id,
                'completed_at' => now(),
            ]);
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);

        $tenant->run(function () use ($host): void {
            $owner = User::query()->where('email', 'owner@reports.test')->firstOrFail();
            $date = now('Asia/Kabul')->toDateString();

            $this->actingAs($owner)->withHeader('Host', $host)
                ->get('/reports?from='.$date.'&to='.$date)
                ->assertOk()
                ->assertSee('POS-RPT-001')
                ->assertSee('90.00');

            $this->actingAs($owner)->withHeader('Host', $host)
                ->get('/reports/export?type=sales&from='.$date.'&to='.$date)
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        });
    }
}
