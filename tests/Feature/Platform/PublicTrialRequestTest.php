<?php

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\PlatformAdmin;
use App\Models\TrialRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PublicTrialRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pharmacy_root_shows_public_trial_request_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Request 7-day trial')
            ->assertSee('BusinessOS Pharmacy')
            ->assertSee('Offline Android POS');
    }

    public function test_public_visitor_can_submit_trial_request_for_platform_review(): void
    {
        $this->post('/trial-request', [
            'pharmacy_name' => 'Kabul Health Pharmacy',
            'owner_name' => 'Trial Applicant',
            'owner_email' => 'trial@applicant.test',
            'phone_whatsapp' => '+93700111222',
            'location' => 'Kabul, Afghanistan',
            'preferred_locale' => 'fa',
            'notes' => 'We need an offline-ready POS.',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'website' => '',
        ])->assertRedirect(route('trial.request'))
            ->assertSessionHas('success');

        $trialRequest = TrialRequest::query()->firstOrFail();

        $this->assertSame('pending', $trialRequest->status);
        $this->assertSame('kabul-health-pharmacy', $trialRequest->requested_slug);
        $this->assertSame('trial@applicant.test', $trialRequest->owner_email);
        $this->assertNotSame('password123', $trialRequest->getRawOriginal('owner_password_ciphertext'));
        $this->assertSame('password123', Crypt::decryptString($trialRequest->getRawOriginal('owner_password_ciphertext')));
    }

    public function test_platform_can_approve_request_and_create_active_seven_day_trial(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $trialRequest = TrialRequest::query()->create([
            'pharmacy_name' => 'Approved Pharmacy',
            'requested_slug' => 'approved-pharmacy',
            'owner_name' => 'Approved Owner',
            'owner_email' => 'approved@pharmacy.test',
            'phone_whatsapp' => '+93700999888',
            'location' => 'Kabul',
            'preferred_locale' => 'fa',
            'owner_password_ciphertext' => Crypt::encryptString('password123'),
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'platform')
            ->post("/platform/trial-requests/{$trialRequest->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('success');

        $trialRequest->refresh()->load('tenant.subscription.license', 'tenant.business');

        $this->assertSame('approved', $trialRequest->status);
        $this->assertNotNull($trialRequest->tenant_id);
        $this->assertNull($trialRequest->getRawOriginal('owner_password_ciphertext'));
        $this->assertSame('application_ready', $trialRequest->tenant->provisioning_status);
        $this->assertSame(SubscriptionStatus::Trial, $trialRequest->tenant->subscription->status);
        $this->assertNotNull($trialRequest->tenant->subscription->license);
        $this->assertSame('Approved Pharmacy', $trialRequest->tenant->business->pharmacy_name);

        app(TenantContext::class)->run($trialRequest->tenant, function (): void {
            $this->assertTrue(User::query()->where('email', 'approved@pharmacy.test')->exists());
        });
    }

    public function test_platform_can_reject_request_without_provisioning_tenant(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $trialRequest = TrialRequest::query()->create([
            'pharmacy_name' => 'Rejected Pharmacy',
            'requested_slug' => 'rejected-pharmacy',
            'owner_name' => 'Rejected Owner',
            'owner_email' => 'rejected@pharmacy.test',
            'phone_whatsapp' => '+93700123456',
            'location' => 'Herat',
            'preferred_locale' => 'ps',
            'owner_password_ciphertext' => Crypt::encryptString('password123'),
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'platform')
            ->post("/platform/trial-requests/{$trialRequest->id}/reject", [
                'decision_notes' => 'Please contact sales for verification.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $trialRequest->refresh();

        $this->assertSame('rejected', $trialRequest->status);
        $this->assertNull($trialRequest->tenant_id);
        $this->assertNull($trialRequest->getRawOriginal('owner_password_ciphertext'));
        $this->assertSame('Please contact sales for verification.', $trialRequest->decision_notes);
    }

    public function test_trial_request_never_claims_a_reserved_system_subdomain(): void
    {
        $this->post('/trial-request', [
            'pharmacy_name' => 'Platform',
            'owner_name' => 'Reserved Name Owner',
            'owner_email' => 'reserved-name@example.test',
            'phone_whatsapp' => '+93700123457',
            'location' => 'Kabul, Afghanistan',
            'preferred_locale' => 'en',
            'notes' => null,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'website' => '',
        ])->assertRedirect(route('trial.request'));

        $trialRequest = TrialRequest::query()->firstOrFail();

        $this->assertSame('platform-2', $trialRequest->requested_slug);
        $this->assertNotContains(
            $trialRequest->requested_slug,
            config('pharmacy.reserved_subdomains'),
        );
    }

}
