<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\DailyClosing;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_daily_closing_is_audited_and_can_be_approved_and_reopened(): void
    {
        $tenant = $this->createTenant(['slug' => 'closing-test']);
        app(TrialProvisioner::class)->provision($tenant);

        $tenant->run(function () use ($tenant): void {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'is_active' => true]);
            $owner->roles()->sync([$roles['owner']->id]);
            $branch = Branch::query()->create(['code' => 'KBL', 'name' => 'Kabul', 'is_default' => true, 'is_active' => true]);
            $location = StockLocation::query()->create(['branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main', 'is_default' => true, 'is_active' => true]);

            $sale = Sale::query()->create([
                'sale_number' => 'POS-TEST',
                'stock_location_id' => $location->id,
                'business_date' => now()->toDateString(),
                'status' => 'completed',
                'currency' => 'AFN',
                'subtotal' => 100,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 100,
                'paid_total' => 100,
                'due_total' => 0,
                'change_total' => 0,
                'payment_status' => 'paid',
                'created_by' => $owner->id,
                'completed_at' => now(),
            ]);

            SalePayment::query()->create([
                'sale_id' => $sale->id,
                'payment_number' => 'PAY-TEST',
                'method' => 'cash',
                'amount' => 100,
                'currency' => 'AFN',
                'paid_at' => now(),
                'created_by' => $owner->id,
            ]);
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);

        $tenant->run(function () use ($host): void {
            $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
            $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

            $this->actingAs($owner)->withHeader('Host', $host)->post('/daily-closing/finalize', [
                'stock_location_id' => $location->id,
                'counted_cash' => 100,
            ])->assertRedirect();

            $closing = DailyClosing::query()->firstOrFail();
            $this->assertSame('finalized', $closing->status);
            $this->assertSame('100.0000', $closing->cash_collected);
            $this->assertSame('100.0000', $closing->expected_cash);
            $this->assertSame(1, $closing->events()->where('event_type', 'finalized')->count());

            $this->actingAs($owner)->withHeader('Host', $host)->post('/daily-closing/'.$closing->id.'/approve')->assertRedirect();
            $this->assertSame('approved', $closing->fresh()->status);

            $this->actingAs($owner)->withHeader('Host', $host)->post('/daily-closing/'.$closing->id.'/reopen', ['reason' => 'Correction required'])->assertRedirect();
            $this->assertSame('reopened', $closing->fresh()->status);
            $this->assertSame(3, $closing->events()->count());
        });
    }
}
