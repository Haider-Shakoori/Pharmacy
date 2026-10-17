<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\WebsiteLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformWebsiteLeadTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->token = str_repeat('a', 64);
        config()->set('pharmacy.marketing_leads.import_token', $this->token);
    }

    private function lead(int $id = 8): array
    {
        return [
            'id' => $id,
            'created_at' => 1792240000,
            'kind' => 'demo',
            'language' => 'fa',
            'name' => 'Website Test',
            'phone' => '+93700000000',
            'email' => 'test@example.test',
            'pharmacy' => 'Test Pharmacy',
            'message' => 'Could you show the POS?',
            'details' => ['city' => 'Kabul', 'computers' => '1'],
        ];
    }

    private function signIn(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'platform');
    }

    public function test_import_rejects_missing_invalid_or_unconfigured_server_key(): void
    {
        $body = ['leads' => [$this->lead()]];
        $this->postJson('/api/v1/marketing/leads/import', $body)->assertUnauthorized();
        $this->withToken('wrong-secret')->postJson('/api/v1/marketing/leads/import', $body)
            ->assertUnauthorized();
        config()->set('pharmacy.marketing_leads.import_token', '');
        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', $body)
            ->assertUnauthorized();
        $this->assertDatabaseCount('website_leads', 0);
    }

    public function test_import_is_idempotent_and_never_resets_staff_classification(): void
    {
        $body = ['leads' => [$this->lead()]];
        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', $body)
            ->assertOk()->assertJsonPath('imported', 1)->assertJsonPath('already_present', 0);

        $lead = WebsiteLead::query()->sole();
        $this->assertSame('darmaltoon.com', $lead->source);
        $this->assertSame(8, $lead->source_id);
        $this->assertSame('fa', $lead->language);
        $this->assertSame(['city' => 'Kabul', 'computers' => '1'], $lead->details);

        $lead->forceFill([
            'status' => 'completed',
            'is_test' => true,
            'internal_notes' => 'Internal test completed; do not call.',
        ])->save();

        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', $body)
            ->assertOk()->assertJsonPath('imported', 0)->assertJsonPath('already_present', 1);

        $this->assertDatabaseCount('website_leads', 1);
        $this->assertTrue($lead->fresh()->is_test);
        $this->assertSame('completed', $lead->fresh()->status);
        $this->assertSame('Internal test completed; do not call.', $lead->fresh()->internal_notes);
    }

    public function test_guest_cannot_see_leads_or_modify_followup(): void
    {
        $lead = WebsiteLead::query()->create([
            'source' => 'darmaltoon.com',
            'source_id' => 9,
            'kind' => 'demo',
            'language' => 'en',
            'contact_name' => 'Private Person',
            'phone' => '+93700000001',
            'status' => 'new',
            'submitted_at' => now(),
        ]);
        $this->get('/platform/demo-requests')
            ->assertRedirect(route('legacy.platform.login'));
        $this->get('/platform/demo-requests/'.$lead->id)
            ->assertRedirect(route('legacy.platform.login'));
        $this->put('/platform/demo-requests/'.$lead->id, [
            'status' => 'completed', 'is_test' => '1',
        ])->assertRedirect(route('legacy.platform.login'));
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_platform_owner_can_complete_internal_test_separately_from_real_demo_requests(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', [
            'leads' => [$this->lead(12), array_merge($this->lead(13), [
                'name' => 'Real Prospect', 'pharmacy' => 'Real Pharmacy',
            ])],
        ])->assertOk()->assertJsonPath('imported', 2);

        $this->signIn();
        $test = WebsiteLead::query()->where('source_id', 12)->sole();

        $this->put('/platform/demo-requests/'.$test->id, [
            'status' => 'completed',
            'is_test' => '1',
            'internal_notes' => 'This was an assistant-generated test.',
        ])->assertRedirect();

        $this->get('/platform/demo-requests')->assertOk()
            ->assertSee('Demo Requests')
            ->assertSee('Real Prospect')
            ->assertDontSee('Website Test');

        $this->get('/platform/demo-requests?test=yes')->assertOk()
            ->assertSee('Website Test')
            ->assertDontSee('Real Prospect');

        $this->get('/platform/demo-requests/'.$test->id)->assertOk()
            ->assertSee('This was an assistant-generated test.')
            ->assertSee('Completed');
        $this->get('/platform')->assertOk()->assertSee('New demo requests');
        $this->assertSame('completed', $test->fresh()->status);
        $this->assertTrue($test->fresh()->is_test);
    }

    public function test_website_trial_enquiry_imports_to_the_private_inbox_without_activating_an_account(): void
    {
        $payload = $this->lead(72);
        $payload['kind'] = 'trial';
        $payload['pharmacy'] = 'Pending Trial Pharmacy';

        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', [
            'leads' => [$payload],
        ])->assertOk()->assertJsonPath('imported', 1);

        $lead = WebsiteLead::query()->where('source_id', 72)->sole();
        $this->assertSame('trial', $lead->kind);
        $this->assertSame('new', $lead->status);
        $this->assertDatabaseCount('trial_requests', 0);
        $this->assertDatabaseCount('subscriptions', 0);

        $this->signIn();
        $this->get('/platform/demo-requests?kind=trial')->assertOk()
            ->assertSee('Pending Trial Pharmacy')
            ->assertSee('7-day trial enquiry');

        $this->get('/platform/demo-requests/'.$lead->id)->assertOk()
            ->assertSee('not an active account');
    }

    public function test_trial_enquiry_cannot_import_without_email_and_pharmacy(): void
    {
        $payload = $this->lead(73);
        $payload['kind'] = 'trial';
        $payload['email'] = null;
        $payload['pharmacy'] = null;

        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', [
            'leads' => [$payload],
        ])->assertUnprocessable()->assertJsonValidationErrors('leads.0.email');

        $this->assertDatabaseCount('website_leads', 0);
    }

    public function test_invalid_import_cannot_store_untrusted_request_payload(): void
    {
        $bad = $this->lead(18);
        $bad['kind'] = 'admin';
        $bad['details'] = ['huge' => str_repeat('x', 300)];

        $this->withToken($this->token)->postJson('/api/v1/marketing/leads/import', [
            'leads' => [$bad],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'leads.0.kind', 'leads.0.details.huge',
        ]);
        $this->assertDatabaseCount('website_leads', 0);
    }
}
