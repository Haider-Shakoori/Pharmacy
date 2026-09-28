<?php

namespace Tests\Unit\Backups;

use App\Services\Backups\BackupManager;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class BackupManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'pharmacy-manifest-test-'
            .bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->root, 0700, true);
        config(['backup.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_manifest_checksum_verification_detects_tampering(): void
    {
        $name = '20260928_010203_test';
        $directory = $this->root.DIRECTORY_SEPARATOR.$name;
        File::ensureDirectoryExists($directory, 0700, true);

        $payload = 'portable mysql dump fixture';
        $file = 'central.sql.gz';
        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.$file,
            $payload,
        );

        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode([
                'version' => 1,
                'name' => $name,
                'created_at' => '2026-09-28T01:02:03Z',
                'entries' => [[
                    'scope' => 'central',
                    'tenant_id' => null,
                    'driver' => 'mysql',
                    'database' => 'pharmacy',
                    'file' => $file,
                    'sha256' => hash('sha256', $payload),
                    'bytes' => strlen($payload),
                ]],
            ], JSON_THROW_ON_ERROR),
        );

        $manager = app(BackupManager::class);
        $this->assertTrue($manager->verify($name)['verified']);

        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.$file,
            'tampered',
        );

        $this->expectException(RuntimeException::class);
        $manager->verify($name);
    }

    public function test_backup_name_cannot_escape_configured_root(): void
    {
        $this->expectException(RuntimeException::class);

        app(BackupManager::class)->verify('../outside');
    }
}
