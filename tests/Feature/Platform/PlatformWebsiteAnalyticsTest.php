<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Tests\TestCase;

class PlatformWebsiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private string $analyticsFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->analyticsFile = sys_get_temp_dir().'/darmaltoon-marketing-test-'.bin2hex(random_bytes(8)).'.sqlite';
        config()->set('pharmacy.marketing_analytics_db_path', $this->analyticsFile);
    }

    protected function tearDown(): void
    {
        if (isset($this->analyticsFile) && is_file($this->analyticsFile)) {
            unlink($this->analyticsFile);
        }
        parent::tearDown();
    }

    private function signInAsOwner(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Marketing Owner',
            'email' => 'marketing-admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'platform');
    }

    private function seedAggregateMetrics(): void
    {
        $db = new PDO('sqlite:'.$this->analyticsFile);
        $db->exec('CREATE TABLE daily_metrics (
            day TEXT NOT NULL, event TEXT NOT NULL, path TEXT NOT NULL,
            language TEXT NOT NULL, source TEXT NOT NULL, referrer TEXT NOT NULL,
            campaign TEXT NOT NULL, device TEXT NOT NULL, count INTEGER NOT NULL DEFAULT 0
        )');
        $insert = $db->prepare('INSERT INTO daily_metrics (day, event, path, language, source, referrer, campaign, device, count)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $date = gmdate('Y-m-d');
        $insert->execute([$date, 'page_view', '/', 'en', 'organic', 'google', '', 'desktop', 12]);
        $insert->execute([$date, 'page_view', '/guides/', 'en', 'ai_referral', 'chatgpt', '', 'desktop', 3]);
        $insert->execute([$date, 'demo_received', '/demo.html', 'en', 'server', 'none', '', 'server', 2]);
    }

    public function test_guest_cannot_open_the_marketing_analytics_panel(): void
    {
        $this->get('/platform/analytics')->assertRedirect(route('legacy.platform.login'));
        $this->post('/platform/analytics/ai-mentions', [
            'engine' => 'ChatGPT', 'question' => 'Which pharmacy inventory software is suitable?',
            'result' => 'mentioned_with_link',
        ])->assertRedirect(route('legacy.platform.login'));
    }

    public function test_platform_owner_can_read_marketing_aggregates_in_same_admin_sidebar(): void
    {
        $this->signInAsOwner();
        $this->seedAggregateMetrics();

        $this->get('/platform/analytics')
            ->assertOk()
            ->assertSee('Website Analytics')
            ->assertSee('AI-attributed visits')
            ->assertSee('ChatGPT', false)
            ->assertSee('12')
            ->assertSee('3')
            ->assertSee('Demo requests received');
    }

    public function test_missing_marketing_database_renders_helpful_message_without_creating_it(): void
    {
        $this->signInAsOwner();
        $this->get('/platform/analytics')
            ->assertOk()
            ->assertSee('Marketing analytics data is not available');

        $this->assertFileDoesNotExist($this->analyticsFile);
    }

    public function test_manual_ai_mentions_must_be_validated_and_are_visible_to_owner(): void
    {
        $this->signInAsOwner();
        $this->seedAggregateMetrics();

        $this->post('/platform/analytics/ai-mentions', [
            'engine' => 'ChatGPT',
            'question' => 'Which pharmacy management software should I evaluate?',
            'result' => 'mentioned_with_link',
            'evidence_url' => 'https://example.test/checked-answer',
        ])->assertRedirect();

        $db = new PDO('sqlite:'.$this->analyticsFile);
        $this->assertSame('1', (string) $db->query('SELECT COUNT(*) FROM ai_mention_checks')->fetchColumn());

        $this->get('/platform/analytics')->assertOk()->assertSee('Which pharmacy management software should I evaluate?');

        $this->post('/platform/analytics/ai-mentions', [
            'engine' => 'UnknownAI',
            'question' => 'Short',
            'result' => 'mentioned_with_link',
        ])->assertSessionHasErrors(['engine', 'question']);

        $this->assertSame('1', (string) $db->query('SELECT COUNT(*) FROM ai_mention_checks')->fetchColumn());
    }
}

