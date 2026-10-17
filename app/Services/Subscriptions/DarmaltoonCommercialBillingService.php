<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\CommercialEnrollment;
use App\Models\CommercialPaymentReceipt;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use App\Support\Commercial\DarmaltoonLaunchPricing as Pricing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DarmaltoonCommercialBillingService
{
    public function __construct(private readonly LicenseKeyService $keys) {}

    public function snapshot(): array
    {
        $confirmed = (int) (DB::connection('central')
            ->table('commercial_launch_counters')->where('id', 1)->value('confirmed_founders') ?? 0);

        return [
            'confirmed_founders' => $confirmed,
            'remaining' => max(0, Pricing::FOUNDING_LIMIT - $confirmed),
            'first_year_price' => Pricing::firstYearPrice($confirmed),
            'regular_first_year_price' => Pricing::REGULAR_FIRST_YEAR,
            'renewal_price' => Pricing::ANNUAL_RENEWAL,
            'currency' => Pricing::CURRENCY,
        ];
    }

    public function recordInitial(Tenant $tenant, PlatformAdmin $admin, string $reference, string $amount): array
    {
        return DB::connection('central')->transaction(function () use ($tenant, $admin, $reference, $amount): array {
            $business = $tenant->business()->lockForUpdate()->firstOrFail();

            if (CommercialEnrollment::query()->where('business_id', $business->id)->exists()) {
                throw ValidationException::withMessages(['payment_reference' => 'This pharmacy has already made its first payment. Record a renewal instead.']);
            }

            $subscription = Subscription::query()->where('business_id', $business->id)->lockForUpdate()->first();
            if ($subscription !== null && ! in_array($subscription->status, [
                SubscriptionStatus::Trial, SubscriptionStatus::Pending, SubscriptionStatus::Expired,
            ], true)) {
                throw ValidationException::withMessages(['payment_reference' => 'An existing non-trial subscription must be reviewed before enrolling this pharmacy.']);
            }

            $plan = $this->activeStandardPlan();

            DB::connection('central')->table('commercial_launch_counters')->insertOrIgnore([
                'id' => 1, 'confirmed_founders' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $counter = DB::connection('central')->table('commercial_launch_counters')
                ->where('id', 1)->lockForUpdate()->first();
            $confirmed = (int) $counter->confirmed_founders;
            $expected = Pricing::firstYearPrice($confirmed);

            if (Pricing::normaliseAmount($amount) !== $expected) {
                throw ValidationException::withMessages(['amount' =>
                    'Payment does not match the current first-year rate of AFN '.$expected.'. Confirm the correct amount before activating.']);
            }

            $founding = $confirmed < Pricing::FOUNDING_LIMIT;
            if ($founding) {
                DB::connection('central')->table('commercial_launch_counters')->where('id', 1)
                    ->update(['confirmed_founders' => $confirmed + 1, 'updated_at' => now()]);
            }

            $paidAt = now();
            $enrollment = CommercialEnrollment::query()->create([
                'business_id' => $business->id,
                'offer_tier' => $founding ? 'founding' : 'standard',
                'founder_number' => $founding ? $confirmed + 1 : null,
                'first_year_amount' => $expected,
                'renewal_amount' => Pricing::ANNUAL_RENEWAL,
                'currency' => Pricing::CURRENCY,
                'confirmed_by' => $admin->id,
                'first_paid_at' => $paidAt,
            ]);

            $subscription ??= new Subscription(['business_id' => $business->id]);
            $subscription->forceFill([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'trial_started_at' => null,
                'trial_ends_at' => null,
                'starts_at' => $paidAt,
                'ends_at' => $paidAt->copy()->addYearNoOverflow(),
                'auto_renew' => false,
            ])->save();

            $this->recordReceipt($enrollment, $admin, 'initial', $expected, $reference, $paidAt, $subscription->ends_at);
            $key = $this->keys->ensureForSubscription($subscription);

            return ['enrollment' => $enrollment, 'new_key' => $key];
        }, 3);
    }

    public function recordRenewal(Tenant $tenant, PlatformAdmin $admin, string $reference, string $amount): CommercialPaymentReceipt
    {
        return DB::connection('central')->transaction(function () use ($tenant, $admin, $reference, $amount): CommercialPaymentReceipt {
            $business = $tenant->business()->lockForUpdate()->firstOrFail();
            $enrollment = CommercialEnrollment::query()->where('business_id', $business->id)->lockForUpdate()->first();
            if ($enrollment === null) {
                throw ValidationException::withMessages(['payment_reference' => 'A confirmed initial payment is required before renewal.']);
            }
            if (Pricing::normaliseAmount($amount) !== (string) $enrollment->renewal_amount) {
                throw ValidationException::withMessages(['amount' => 'Payment must match this customer\'s agreed renewal price of AFN '.$enrollment->renewal_amount.'.']);
            }

            $subscription = Subscription::query()->where('business_id', $business->id)->lockForUpdate()->firstOrFail();
            if ($subscription->plan?->code !== Pricing::PLAN_CODE) {
                throw ValidationException::withMessages(['payment_reference' => 'The subscription plan has changed; review it before renewing.']);
            }
            if ($subscription->status === SubscriptionStatus::Cancelled) {
                throw ValidationException::withMessages(['payment_reference' => 'Cancelled subscriptions require manual review before renewal.']);
            }
            $now = now();
            $start = $subscription->ends_at !== null && $subscription->ends_at->greaterThan($now)
                ? $subscription->ends_at->copy() : $now->copy();

            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $start->addYearNoOverflow(),
            ])->save();

            return $this->recordReceipt($enrollment, $admin, 'renewal', (string) $enrollment->renewal_amount,
                $reference, $now, $subscription->ends_at);
        }, 3);
    }

    private function activeStandardPlan(): Plan
    {
        $plan = Plan::query()->where('code', Pricing::PLAN_CODE)->first();
        if ($plan === null || ! $plan->is_active || $plan->billing_period->value !== 'yearly'
            || $plan->currency !== Pricing::CURRENCY || $plan->max_windows_devices !== 1
            || $plan->max_android_devices !== 2 || $plan->max_branches !== 1
            || (string) $plan->price !== Pricing::REGULAR_FIRST_YEAR) {
            throw ValidationException::withMessages(['payment_reference' =>
                'The Darmaltoon Standard Annual plan must be configured and enabled at AFN 7,000, yearly, 1 Windows / 2 Android / 1 branch before payment confirmation.']);
        }

        return $plan;
    }

    private function recordReceipt(CommercialEnrollment $enrollment, PlatformAdmin $admin, string $kind,
        string $amount, string $reference, Carbon $paidAt, Carbon $coverageEndsAt): CommercialPaymentReceipt
    {
        return CommercialPaymentReceipt::query()->create([
            'enrollment_id' => $enrollment->id,
            'kind' => $kind,
            'amount' => $amount,
            'currency' => Pricing::CURRENCY,
            'payment_reference' => $reference,
            'paid_at' => $paidAt,
            'coverage_ends_at' => $coverageEndsAt,
            'confirmed_by' => $admin->id,
        ]);
    }
}
