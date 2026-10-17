<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\CommercialEnrollment;
use App\Models\CommercialPaymentReceipt;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\DarmaltoonCommercialBillingService;
use App\Support\Commercial\DarmaltoonLaunchPricing as Pricing;
use Database\Seeders\DarmaltoonLaunchPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarmaltoonFoundingPricingTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = PlatformAdmin::query()->create([
            'name' => 'Payment Reviewer',
            'email' => 'finance@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($this->admin, 'platform');
        $this->seed(DarmaltoonLaunchPlanSeeder::class);
        Plan::query()->where('code', Pricing::PLAN_CODE)->update(['is_active' => true]);
    }

    private function customer(int $number): Tenant
    {
        // No tenant database is needed to validate central commercial rules.
        $tenant = Tenant::query()->create(['provisioning_status' => 'application_ready']);
        Business::query()->create([
            'tenant_id' => $tenant->id,
            'pharmacy_name' => 'Customer '.$number,
            'slug' => 'launch-pharmacy-'.$number,
            'owner_email' => 'customer'.$number.'@example.test',
            'billing_currency' => 'AFN',
        ]);

        return $tenant;
    }

    private function firstPayment(Tenant $tenant, int $number, int $amount): void
    {
        $this->post(route('legacy.platform.subscriptions.commercial.initial', $tenant), [
            'amount' => $amount,
            'payment_reference' => 'CASH-2026-'.$number,
            'payment_verified' => '1',
        ])->assertRedirect();
    }

    public function test_seed_creates_inactive_standard_plan_without_overwriting_existing_data(): void
    {
        $plan = Plan::query()->where('code', Pricing::PLAN_CODE)->sole();
        $this->assertSame('7000.00', (string) $plan->price);
        $this->assertSame(1, $plan->max_windows_devices);
        $this->assertSame(2, $plan->max_android_devices);
        $this->assertSame(1, $plan->max_branches);
        $plan->update(['description' => 'Customized existing description']);
        $this->seed(DarmaltoonLaunchPlanSeeder::class);
        $this->assertSame('Customized existing description', $plan->fresh()->description);
    }

    public function test_first_ten_verified_paying_pharmacies_get_5000_and_customer_11_gets_7000(): void
    {
        $customers = [];
        for ($i = 1; $i <= 10; $i++) {
            $tenant = $this->customer($i);
            $customers[] = $tenant;
            $this->firstPayment($tenant, $i, 5000);
        }

        $this->assertSame(10, CommercialEnrollment::query()->where('offer_tier', 'founding')->count());
        $this->assertSame(
            range(1, 10),
            CommercialEnrollment::query()->where('offer_tier', 'founding')->orderBy('founder_number')
                ->pluck('founder_number')->all(),
        );
        $this->assertSame(10, CommercialPaymentReceipt::query()->where('kind', 'initial')->count());
        $this->assertSame(0, app(DarmaltoonCommercialBillingService::class)->snapshot()['remaining']);

        $eleventh = $this->customer(11);
        $this->post(route('legacy.platform.subscriptions.commercial.initial', $eleventh), [
            'amount' => '5000.00',
            'payment_reference' => 'CASH-2026-11-BAD',
            'payment_verified' => '1',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('commercial_enrollments', ['business_id' => $eleventh->business->id]);
        $this->firstPayment($eleventh, 11, 7000);

        $regular = CommercialEnrollment::query()->where('business_id', $eleventh->business->id)->sole();
        $this->assertSame('standard', $regular->offer_tier);
        $this->assertNull($regular->founder_number);
        $this->assertSame('7000.00', $regular->first_year_amount);
        $this->assertSame('5000.00', $regular->renewal_amount);
    }

    public function test_demo_trial_and_manual_active_status_do_not_consume_paid_founder_places(): void
    {
        $tenant = $this->customer(1);
        $this->get(route('legacy.platform.subscriptions.edit', $tenant))->assertOk()->assertSee('10 of 10 founder places remain');
        $this->assertSame(10, app(DarmaltoonCommercialBillingService::class)->snapshot()['remaining']);

        $plan = Plan::query()->where('code', Pricing::PLAN_CODE)->sole();
        $this->put(route('legacy.platform.subscriptions.update', $tenant), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'auto_renew' => '0',
        ])->assertSessionHasErrors('status');
        $this->assertSame(10, app(DarmaltoonCommercialBillingService::class)->snapshot()['remaining']);
    }

    public function test_verified_renewal_uses_immutable_5000_rate_without_claiming_another_slot(): void
    {
        $tenant = $this->customer(1);
        $this->firstPayment($tenant, 1, 5000);

        $enrollment = CommercialEnrollment::query()->where('business_id', $tenant->business->id)->sole();
        $oldEnd = Subscription::query()->where('business_id', $tenant->business->id)->sole()->ends_at;

        $this->post(route('legacy.platform.subscriptions.commercial.renew', $tenant), [
            'amount' => '7000.00', 'payment_reference' => 'RENEW-ERROR',
            'payment_verified' => '1',
        ])->assertSessionHasErrors('amount');

        $this->post(route('legacy.platform.subscriptions.commercial.renew', $tenant), [
            'amount' => 5000, 'payment_reference' => 'RENEW-2027-1',
            'payment_verified' => '1',
        ])->assertRedirect();

        $this->assertSame(9, app(DarmaltoonCommercialBillingService::class)->snapshot()['remaining']);
        $this->assertSame(2, $enrollment->receipts()->count());
        $this->assertSame('5000.00', $enrollment->receipts()->where('kind', 'renewal')->sole()->amount);
        $this->assertTrue(Subscription::query()->where('business_id', $tenant->business->id)
            ->sole()->ends_at->greaterThan($oldEnd));
    }

    public function test_duplicate_payment_reference_and_unverified_payment_do_not_count_twice(): void
    {
        $one = $this->customer(1);
        $two = $this->customer(2);
        $this->post(route('legacy.platform.subscriptions.commercial.initial', $one), [
            'amount' => '5000.00', 'payment_reference' => 'INCOMPLETE',
        ])->assertSessionHasErrors('payment_verified');
        $this->firstPayment($one, 1, 5000);

        $this->post(route('legacy.platform.subscriptions.commercial.initial', $one), [
            'amount' => 5000, 'payment_reference' => 'DIFFERENT-REF',
            'payment_verified' => '1',
        ])->assertSessionHasErrors('payment_reference');

        $this->post(route('legacy.platform.subscriptions.commercial.initial', $two), [
            'amount' => 5000, 'payment_reference' => 'CASH-2026-1',
            'payment_verified' => '1',
        ])->assertSessionHasErrors('payment_reference');

        $this->assertSame(9, app(DarmaltoonCommercialBillingService::class)->snapshot()['remaining']);
        $this->assertSame(1, CommercialEnrollment::query()->count());
    }

    public function test_public_offer_discloses_only_aggregate_without_customer_details(): void
    {
        $response = $this->getJson('/api/v1/marketing/launch-offer')->assertOk()
            ->assertJsonPath('founding_remaining', 10)
            ->assertJsonPath('first_year_price_afn', '5000.00')
            ->assertJsonPath('renewal_price_afn', '5000.00');
        $this->assertStringNotContainsString('owner_email', $response->getContent());
    }

    public function test_guests_cannot_mark_a_payment_as_verified(): void
    {
        $tenant = $this->customer(4);
        auth('platform')->logout();

        $this->post(route('legacy.platform.subscriptions.commercial.initial', $tenant), [
            'amount' => 5000, 'payment_reference' => 'MALICIOUS',
            'payment_verified' => '1',
        ])->assertRedirect(route('legacy.platform.login'));

        $this->assertDatabaseCount('commercial_enrollments', 0);
    }
}
