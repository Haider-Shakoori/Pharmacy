<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionHealth;
use App\Enums\SubscriptionStatus;
use App\Models\LicenseActivation;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PlatformSubscriptionMonitoringService
{
    public const DEVICE_STALE_AFTER_HOURS = 24;

    public function __construct(
        private readonly SubscriptionHealthService $health,
    ) {}

    public function snapshot(): array
    {
        $now = CarbonImmutable::now();
        $staleBefore = $now->subHours(self::DEVICE_STALE_AFTER_HOURS);

        $tenants = Tenant::query()
            ->with([
                'business.subscription.plan',
                'business.subscription.license.activations',
            ])
            ->orderByDesc('created_at')
            ->get();

        $rows = $tenants
            ->map(fn (Tenant $tenant): array => $this->tenantRow($tenant, $now, $staleBefore))
            ->sortBy([
                ['severity_rank', 'desc'],
                ['deadline_sort', 'asc'],
                ['pharmacy_name', 'asc'],
            ])
            ->values();

        return [
            'rows' => $rows,
            'metrics' => [
                'total_pharmacies' => $rows->count(),
                'subscriptions' => $rows->whereNotNull('subscription_id')->count(),
                'operational' => $rows->where('operational', true)->count(),
                'trials' => $rows->where('subscription_status', SubscriptionStatus::Trial->value)->count(),
                'expiring' => $rows->where('health', SubscriptionHealth::Expiring->value)->count(),
                'attention' => $rows->where('needs_attention', true)->count(),
                'no_subscription' => $rows->where('health', SubscriptionHealth::NoSubscription->value)->count(),
                'active_devices' => $rows->sum('active_devices'),
                'stale_devices' => $rows->sum('stale_devices'),
            ],
        ];
    }

    private function tenantRow(
        Tenant $tenant,
        CarbonImmutable $now,
        CarbonImmutable $staleBefore,
    ): array {
        $subscription = $tenant->business?->subscription;
        $health = $this->health->forTenant($tenant);
        $license = $subscription?->license;
        $plan = $subscription?->plan;

        $activeActivations = $license?->activations
            ->filter(fn (LicenseActivation $activation): bool => $activation->revoked_at === null)
            ->values() ?? collect();

        $staleActivations = $activeActivations
            ->filter(function (LicenseActivation $activation) use ($staleBefore): bool {
                $lastContact = $activation->last_seen_at ?? $activation->activated_at;

                return $lastContact === null || $lastContact->lessThanOrEqualTo($staleBefore);
            })
            ->values();

        $lastSeenAt = $activeActivations
            ->map(fn (LicenseActivation $activation) => $activation->last_seen_at ?? $activation->activated_at)
            ->filter()
            ->sortDesc()
            ->first();

        $deadline = $subscription?->status === SubscriptionStatus::Trial
            ? $subscription->trial_ends_at
            : $subscription?->ends_at;

        $trialEndingSoon = $subscription?->status === SubscriptionStatus::Trial
            && $deadline !== null
            && $deadline->isFuture()
            && $deadline->lessThanOrEqualTo($now->addDays(2));

        $maxDevices = $plan?->max_android_devices;
        $activeDeviceCount = $activeActivations->count();
        $overDeviceLimit = $maxDevices !== null && $activeDeviceCount > $maxDevices;

        $reasons = collect();

        if (! $health->isOperational()) {
            $reasons->push($health->label());
        } elseif ($health === SubscriptionHealth::Expiring) {
            $reasons->push('Subscription expires within 7 days');
        }

        if ($trialEndingSoon) {
            $reasons->push('Trial ends within 2 days');
        }

        if ($staleActivations->isNotEmpty()) {
            $reasons->push($staleActivations->count().' device(s) have not checked in for 24 hours');
        }

        if ($overDeviceLimit) {
            $reasons->push('Active devices exceed the current plan limit');
        }

        $critical = ! $health->isOperational() || $overDeviceLimit;
        $needsAttention = $critical || $health === SubscriptionHealth::Expiring || $trialEndingSoon || $staleActivations->isNotEmpty();

        return [
            'tenant_id' => (string) $tenant->id,
            'pharmacy_name' => $tenant->name ?? 'Unnamed pharmacy',
            'slug' => $tenant->slug ?? (string) $tenant->id,
            'tenant_status' => $tenant->status->value,
            'subscription_id' => $subscription?->id,
            'subscription_status' => $subscription?->status->value,
            'health' => $health->value,
            'health_label' => $health->label(),
            'operational' => $health->isOperational(),
            'plan_name' => $plan?->name,
            'deadline' => $deadline,
            'deadline_sort' => $deadline?->timestamp ?? PHP_INT_MAX,
            'license_status' => $license?->status->value,
            'license_hint' => $license?->key_hint,
            'active_devices' => $activeDeviceCount,
            'stale_devices' => $staleActivations->count(),
            'max_devices' => $maxDevices,
            'last_seen_at' => $lastSeenAt,
            'over_device_limit' => $overDeviceLimit,
            'needs_attention' => $needsAttention,
            'severity' => $critical ? 'critical' : ($needsAttention ? 'warning' : 'ok'),
            'severity_rank' => $critical ? 2 : ($needsAttention ? 1 : 0),
            'reasons' => $reasons->unique()->values()->all(),
        ];
    }
}
