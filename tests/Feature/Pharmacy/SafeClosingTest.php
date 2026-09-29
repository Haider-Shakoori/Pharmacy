<?php

namespace Tests\Feature\Pharmacy;

use App\Models\CashierShift;
use App\Models\CashSafe;
use App\Models\SafeMovement;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\DailyClosing\DailyClosingService;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Safe\SafeService;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SafeClosingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_safe_receives_closed_pos_cash_and_closes_after_daily_closing(): void
    {
        $tenant = $this->createTenant(['name' => 'Safe Pharmacy', 'slug' => 'safe-pharmacy']);
        app(TrialProvisioner::class)->provision($tenant);
        app(InventoryProvisioner::class)->ensureDefaults($tenant);
        app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@safe.test', 'password');

        $tenant->run(function (): void {
            $owner = User::query()->where('email', 'owner@safe.test')->firstOrFail();
            $location = StockLocation::query()->where('is_default', true)->firstOrFail();
            $service = app(SafeService::class);
            $safe = $service->ensureDefaultSafe();

            $service->postManual($safe, $owner, [
                'movement_type' => 'owner_deposit',
                'amount' => 10000,
                'reference' => 'OPENING',
                'reason' => 'Opening safe cash',
                'idempotency_key' => 'safe-opening-test',
            ]);
            $this->assertSame('10000.0000', $service->balance($safe));

            $shift = CashierShift::query()->create([
                'stock_location_id' => $location->id,
                'user_id' => $owner->id,
                'business_date' => today(),
                'status' => 'closed',
                'opening_cash' => 500,
                'expected_cash' => 800,
                'counted_cash' => 800,
                'variance' => 0,
                'opened_at' => now()->subHours(3),
                'closed_at' => now()->subHour(),
            ]);

            $pending = $service->pendingShiftTransfers($safe);
            $this->assertCount(1, $pending);
            $this->assertSame('300.0000', $pending->first()['suggested']);

            try {
                $service->finalize($safe, $owner, '10000', null);
                $this->fail('Safe Closing should reject pending POS transfers.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('closing', $exception->errors());
            }

            $service->receiveFromShift($safe, $shift, $owner, '300', 'safe-pos-transfer-test');
            $this->assertSame('10300.0000', $service->balance($safe));
            $this->assertTrue($service->pendingShiftTransfers($safe)->isEmpty());

            $secondSafe = CashSafe::query()->create([
                'stock_location_id' => $location->id,
                'code' => 'SECOND-SAFE',
                'name' => 'Second Safe',
                'is_active' => true,
            ]);
            $this->assertTrue($service->pendingShiftTransfers($secondSafe)->isEmpty(), 'A closed shift must not be transferable twice across different safes.');

            app(DailyClosingService::class)->finalize($location, $owner, '500', null);
            $closing = $service->finalize($safe, $owner, '10300', null);

            $this->assertSame('finalized', $closing->status);
            $this->assertSame('0.0000', $closing->variance);

            try {
                $service->postManual($safe, $owner, [
                    'movement_type' => 'bank_deposit',
                    'amount' => 100,
                    'reason' => 'Should be blocked',
                    'idempotency_key' => 'blocked-after-close',
                ]);
                $this->fail('Posting into a finalized safe should fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('safe', $exception->errors());
            }

            $service->approve($closing, $owner);
            $this->assertSame('approved', $closing->fresh()->status);
            $service->reopen($closing->fresh(), $owner, 'Correction required');
            $this->assertSame('reopened', $closing->fresh()->status);
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);
        $tenant->run(function () use ($host): void {
            $owner = User::query()->where('email', 'owner@safe.test')->firstOrFail();
            $this->actingAs($owner)->withHeader('Host', $host)->get('/safe')
                ->assertOk()
                ->assertSee('Cash Safe')
                ->assertSee('Safe Closing')
                ->assertSee('Full screen');
            $this->assertSame(1, SafeMovement::query()->where('movement_type', 'pos_transfer')->count());
        });
    }
}
