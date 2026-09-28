<?php

namespace Tests\Unit\Backups;

use App\Services\Backups\DatabaseSnapshotService;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class DatabaseSnapshotServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'pharmacy-backup-test-'
            .bin2hex(random_bytes(6));

        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        DB::purge('backup_fixture');

        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_file_backed_sqlite_can_be_backed_up_verified_and_restored(): void
    {
        $database = $this->directory.DIRECTORY_SEPARATOR.'source.sqlite';
        $snapshot = $this->directory.DIRECTORY_SEPARATOR.'snapshot.sqlite';

        $pdo = new PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE medicines (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO medicines (name) VALUES ('Paracetamol')");
        $pdo = null;

        $config = [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        config(['database.connections.backup_fixture' => $config]);

        $service = app(DatabaseSnapshotService::class);
        $service->backup('backup_fixture', $config, $snapshot);

        $this->assertFileExists($snapshot);
        $this->assertTrue($service->integrityCheck($snapshot));

        DB::connection('backup_fixture')
            ->table('medicines')
            ->delete();

        $service->restore('backup_fixture', $config, $snapshot);

        $this->assertSame(
            'Paracetamol',
            DB::connection('backup_fixture')
                ->table('medicines')
                ->value('name'),
        );
    }
}
