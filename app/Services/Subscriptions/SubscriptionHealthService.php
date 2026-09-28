<?php

namespace App\Services\Subscriptions;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionHealth;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\Tenant;

class SubscriptionHealthService
{
    public function forTenant(Tenant $tenant): SubscriptionHealth
    {
        if ($tenant->status !== TenantStatus::Active) {
            return SubscriptionHealth::InactiveTenant;
        }

        $subscription = null;

        if ($tenant->relationLoaded('business') &&
            $tenant->business?->relationLoaded('subscription')) {
            $subscription = $tenant->business->subscription;

            if ($subscription !== null) {
                $subscription->setRelation('business', $tenant->business);
                $tenant->business->setRelation('tenant', $tenant);
            }
        } else {
            $tenant->loadMissing('subscription.license');
            $subscription = $tenant->subscription;
        }

        if ($subscription === null) {
            return SubscriptionHealth::NoSubscription;
        }

        return $this->forSubscription($subscription);
    }

    public function forSubscription(Subscription $subscription): SubscriptionHealth
    {
        $subscription->loadMissing('business.tenant', 'license');

        if ($subscription->business->tenant->status !== TenantStatus::Active) {
            return SubscriptionHealth::InactiveTenant;
        }

        if ($subscription->license === null) {
            return SubscriptionHealth::LicenseMissing;
        }

        if ($subscription->license->status !== LicenseStatus::Active || $subscription->license->revoked_at !== null) {
            return SubscriptionHealth::LicenseRevoked;
        }

        if ($subscription->starts_at !== null && $subscription->starts_at->isFuture()) {
            return SubscriptionHealth::NotStarted;
        }

        return match ($subscription->status) {
            SubscriptionStatus::Trial => $this->trialHealth($subscription),
            SubscriptionStatus::Active => $this->activeHealth($subscription),
            SubscriptionStatus::Pending => SubscriptionHealth::NotStarted,
            SubscriptionStatus::Suspended => SubscriptionHealth::Suspended,
            SubscriptionStatus::Expired => SubscriptionHealth::Expired,
            SubscriptionStatus::Cancelled => SubscriptionHealth::Cancelled,
        };
    }

    private function trialHealth(Subscription $subscription): SubscriptionHealth
    {
        if ($subscription->trial_ends_at === null || ! $subscription->trial_ends_at->isFuture()) {
            return SubscriptionHealth::Expired;
        }

        return SubscriptionHealth::Trial;
    }

    private function activeHealth(Subscription $subscription): SubscriptionHealth
    {
        if ($subscription->ends_at === null) {
            return SubscriptionHealth::Healthy;
        }

        if (! $subscription->ends_at->isFuture()) {
            return SubscriptionHealth::Expired;
        }

        if ($subscription->ends_at->lessThanOrEqualTo(now()->addDays(7))) {
            return SubscriptionHealth::Expiring;
        }

        return SubscriptionHealth::Healthy;
    }
}
