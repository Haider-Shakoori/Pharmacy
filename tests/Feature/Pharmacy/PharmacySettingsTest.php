<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Tenant;
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

    public function test_owner_can_update_profile_localization_and_daily_closing_policy(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@example.test', 'password123');

        $this->actingAs($owner)
            ->withSession(['tenant_id' => $tenant->id])
            ->put('/pharmacy/settings', [
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

        $tenant->refresh();
        $closing = app(PharmacySettings::class)->dailyClosing($tenant);

        $this->assertSame('Kabul City Pharmacy', $tenant->name);
        $this->assertSame('fa', $tenant->locale);
        $this->assertSame('02:30', $closing['business_day_rollover_time']);
        $this->assertEquals(100.0, $closing['variance_note_threshold']);
        $this->assertTrue($closing['require_counted_cash']);
        $this->assertTrue($closing['allow_reopen']);
    }

    public function test_cashier_cannot_open_settings(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
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

        $this->actingAs($cashier)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/pharmacy/settings')
            ->assertForbidden();
    }
}
