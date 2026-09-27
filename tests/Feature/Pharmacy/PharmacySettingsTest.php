<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Settings\PharmacySettings;
use App\Services\Subscriptions\TrialProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_update_tenant_local_profile_and_daily_closing_policy(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@example.test', 'password123');

        $this->onTenantDomain($tenant)
            ->actingAs($owner)
            ->put('/settings', [
                'name' => 'Kabul City Pharmacy',
                'timezone' => 'Asia/Kabul',
                'locale' => 'fa',
                'phone' => '+93 700 000 000',
                'address' => 'Kabul',
                'receipt_footer' => 'Thank you',
                'business_day_rollover_time' => '02:30',
                'opening_cash_mode' => 'carry_forward',
                'require_counted_cash' => '1',
                'variance_note_threshold' => '100',
                'allow_reopen' => '1',
                'require_close_before_next_day' => '1',
                'block_online_sales_after_close' => '1',
                'warn_unsynced_devices_before_close' => '1',
            ])
            ->assertRedirect();

        $profile = app(PharmacySettings::class)->profile($tenant);
        $closing = app(PharmacySettings::class)->dailyClosing($tenant);

        $this->assertSame('Kabul City Pharmacy', $profile['name']);
        $this->assertSame('fa', $profile['locale']);
        $this->assertSame('Kabul Pharmacy', $tenant->fresh()->business->pharmacy_name);
        $this->assertSame('02:30', $closing['business_day_rollover_time']);
        $this->assertEquals(100.0, $closing['variance_note_threshold']);
        $this->assertTrue($closing['require_counted_cash']);
        $this->assertTrue($closing['allow_reopen']);
    }

    public function test_cashier_cannot_open_settings(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        app(TrialProvisioner::class)->provision($tenant);
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $cashier = app(TenantContext::class)->run($tenant, function () use ($roles) {
            $user = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@example.test',
                'password' => 'password123',
            ]);
            $user->roles()->sync([$roles['cashier']->id]);

            return $user;
        });

        $this->onTenantDomain($tenant)
            ->actingAs($cashier)
            ->get('/settings')
            ->assertForbidden();
    }
}
