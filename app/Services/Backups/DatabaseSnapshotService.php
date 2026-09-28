<?php

namespace App\Services\Backups;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseSnapshotService
{
    public function extension(array $config): string
    {
        return match ((string) ($config['driver'] ?? '')) {
            'sqlite' => 'sqlite',
            'mysql', 'mariadb' => 'sql.gz',
            default => throw new RuntimeException(
                'Database backup driver is not supported.',
            ),
        };
    }

    public function backup(
        string $connectionName,
        array $config,
        string $destination,
    ): void {
        $driver = (string) ($config['driver'] ?? '');

        match ($driver) {
            'sqlite' => $this->backupSqlite(
                $connectionName,
                $config,
                $destination,
            ),
            'mysql', 'mariadb' => $this->backupMysql(
                $config,
                $destination,
            ),
            default => throw new RuntimeException(
                'Database backup driver is not supported: '.$driver,
            ),
        };
    }

    public function restore(
        string $connectionName,
        array $config,
        string $snapshot,
    ): void {
        $driver = (string) ($config['driver'] ?? '');

        match ($driver) {
            'sqlite' => $this->restoreSqlite(
                $connectionName,
                $config,
                $snapshot,
            ),
            'mysql', 'mariadb' => $this->restoreMysql(
                $config,
                $snapshot,
            ),
            default => throw new RuntimeException(
                'Database restore driver is not supported: '.$driver,
            ),
        };
    }

    public function integrityCheck(string $snapshot): bool
    {
        if (! is_file($snapshot)) {
            return false;
        }

        $pdo = new PDO('sqlite:'.$snapshot);
        $result = $pdo->query('PRAGMA integrity_check')?->fetchColumn();

        return $result === 'ok';
    }

    private function backupSqlite(
        string $connectionName,
        array $config,
        string $destination,
    ): void {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException(
                'SQLite backup requires a file-backed database.',
            );
        }

        if (! is_file($database)) {
            throw new RuntimeException(
                'SQLite database file does not exist: '.$database,
            );
        }

        DB::connection($connectionName)->statement(
            'PRAGMA wal_checkpoint(FULL)',
        );

        if (! copy($database, $destination)) {
            throw new RuntimeException('Unable to copy SQLite backup.');
        }

        @chmod($destination, 0600);

        if (! $this->integrityCheck($destination)) {
            @unlink($destination);

            throw new RuntimeException(
                'SQLite backup failed its integrity check.',
            );
        }
    }

    private function restoreSqlite(
        string $connectionName,
        array $config,
        string $snapshot,
    ): void {
        if (! $this->integrityCheck($snapshot)) {
            throw new RuntimeException(
                'SQLite snapshot failed its integrity check.',
            );
        }

        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException(
                'SQLite restore requires a file-backed database.',
            );
        }

        DB::purge($connectionName);

        if (! copy($snapshot, $database)) {
            throw new RuntimeException('Unable to restore SQLite database.');
        }

        @chmod($database, 0600);
        DB::purge($connectionName);
    }

    private function backupMysql(array $config, string $destination): void
    {
        $handle = gzopen($destination, 'wb9');

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to open MySQL backup destination.',
            );
        }

        $stderr = '';

        try {
            $process = new Process(
                $this->mysqlArguments(
                    (string) config('backup.mysqldump_binary', 'mysqldump'),
                    $config,
                    [
                        '--single-transaction',
                        '--quick',
                        '--skip-lock-tables',
                        '--default-character-set=utf8mb4',
                    ],
                ),
                null,
                $this->mysqlEnvironment($config),
            );
            $process->setTimeout(
                (int) config('backup.process_timeout_seconds', 600),
            );

            $process->run(
                function (string $type, string $buffer) use (
                    $handle,
                    &$stderr,
                ): void {
                    if ($type === Process::OUT) {
                        gzwrite($handle, $buffer);

                        return;
                    }

                    $stderr .= $buffer;
                },
            );
        } finally {
            gzclose($handle);
        }

        if (! isset($process) || ! $process->isSuccessful()) {
            @unlink($destination);

            throw new RuntimeException(
                'mysqldump failed: '.trim($stderr),
            );
        }

        @chmod($destination, 0600);
    }

    private function restoreMysql(array $config, string $snapshot): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'pharmacy-restore-');

        if ($temporary === false) {
            throw new RuntimeException(
                'Unable to create temporary restore file.',
            );
        }

        $input = gzopen($snapshot, 'rb');
        $output = fopen($temporary, 'wb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                gzclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($temporary);

            throw new RuntimeException(
                'Unable to prepare compressed MySQL restore.',
            );
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);

                if ($chunk === false || fwrite($output, $chunk) === false) {
                    throw new RuntimeException(
                        'Unable to decompress MySQL restore.',
                    );
                }
            }
        } finally {
            gzclose($input);
            fclose($output);
        }

        $stream = fopen($temporary, 'rb');

        if ($stream === false) {
            @unlink($temporary);

            throw new RuntimeException(
                'Unable to open MySQL restore input.',
            );
        }

        try {
            $process = new Process(
                $this->mysqlArguments(
                    (string) config('backup.mysql_binary', 'mysql'),
                    $config,
                    ['--default-character-set=utf8mb4'],
                ),
                null,
                $this->mysqlEnvironment($config),
                $stream,
            );
            $process->setTimeout(
                (int) config('backup.process_timeout_seconds', 600),
            );
            $process->run();
        } finally {
            fclose($stream);
            @unlink($temporary);
        }

        if (! isset($process) || ! $process->isSuccessful()) {
            throw new RuntimeException(
                'MySQL restore failed: '.trim(
                    $process?->getErrorOutput() ?? '',
                ),
            );
        }
    }

    private function mysqlArguments(
        string $binary,
        array $config,
        array $options,
    ): array {
        $arguments = [$binary, ...$options];

        $socket = (string) ($config['unix_socket'] ?? '');

        if ($socket !== '') {
            $arguments[] = '--socket='.$socket;
        } else {
            $arguments[] = '--host='.(string) (
                $config['host'] ?? '127.0.0.1'
            );
            $arguments[] = '--port='.(string) (
                $config['port'] ?? '3306'
            );
        }

        $arguments[] = '--user='.(string) ($config['username'] ?? '');
        $arguments[] = (string) ($config['database'] ?? '');

        return $arguments;
    }

    private function mysqlEnvironment(array $config): array
    {
        return [
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ];
    }
}
