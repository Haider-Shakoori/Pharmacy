<?php

namespace Tests\Feature\Subscriptions;

use App\Http\Middleware\EnsureOperationalSubscription;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalSubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_trial_is_allowed(): void
    {
        [$tenant] = $this->trial(now()->addDays(7));
        app(TenantContext::class)->set($tenant);

        $response = app(EnsureOperationalSubscription::class)->handle(
            Request::create('/pharmacy', 'GET'),
            fn () => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_expired_trial_is_blocked_with_payment_required(): void
    {
        [$tenant] = $this->trial(now()->subMinute());
        app(TenantContext::class)->set($tenant);

        try {
            app(EnsureOperationalSubscription::class)->handle(
                Request::create('/pharmacy', 'GET'),
                fn () => response('ok'),
            );

            $this->fail('Expired trial was not blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(402, $exception->getStatusCode());
        }
    }

    private function trial($trialEnd): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Trial Pharmacy '.uniqid(),
            'slug' => 'trial-'.uniqid(),
        ]);
        $plan = Plan::query()->create([
            'name' => 'Trial '.uniqid(),
            'code' => 'TRIAL_'.strtoupper(uniqid()),
            'price' => 0,
            'billing_period' => 'monthly',
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_started_at' => now(),
            'trial_ends_at' => $trialEnd,
            'starts_at' => now(),
            'auto_renew' => false,
        ]);

        app(LicenseKeyService::class)->rotate($subscription);

        return [$tenant, $subscription];
    }
}
