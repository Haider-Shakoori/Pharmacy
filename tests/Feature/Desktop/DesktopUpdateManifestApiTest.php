<?php

namespace Tests\Feature\Desktop;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DesktopUpdateManifestApiTest extends TestCase
{
    private string $manifestRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestRoot = storage_path('framework/testing/desktop-updates-'.uniqid());
        File::makeDirectory($this->manifestRoot.'/stable', 0755, true);
        config(['pharmacy.desktop_updates.manifest_directory' => $this->manifestRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestRoot);
        parent::tearDown();
    }

    public function test_missing_signed_release_returns_service_unavailable_instead_of_404(): void
    {
        $this->getJson('/api/v1/desktop/update/manifest?channel=stable&current=1.0.0&mode=Standalone')
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'No signed production update is currently published for this desktop mode.',
            );
    }

    public function test_published_signed_manifest_is_returned_for_requested_mode(): void
    {
        $manifest = [
            'schema_version' => 1,
            'channel' => 'stable',
            'version' => '1.0.1',
            'api_version' => 'v1',
            'minimum_supported_version' => '1.0.0',
            'minimum_server_version' => null,
            'package_url' => 'https://pharmacy.businessos.af/downloads/desktop/1.0.1/standalone.zip',
            'package_sha256' => str_repeat('A', 64),
            'published_at' => '2026-10-01T00:00:00.0000000Z',
            'release_notes' => 'Release test',
            'signature' => base64_encode(str_repeat('s', 384)),
        ];

        File::put(
            $this->manifestRoot.'/stable/standalone.json',
            json_encode($manifest, JSON_THROW_ON_ERROR),
        );

        $this->getJson('/api/v1/desktop/update/manifest?channel=stable&current=1.0.0&mode=Standalone')
            ->assertOk()
            ->assertJsonPath('version', '1.0.1')
            ->assertJsonPath('package_url', $manifest['package_url'])
            ->assertHeader('Cache-Control', 'max-age=300, private');
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $this->getJson('/api/v1/desktop/update/manifest?channel=stable&current=1.0.0&mode=Unknown')
            ->assertStatus(422);
    }
}