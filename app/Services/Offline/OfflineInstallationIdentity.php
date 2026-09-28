<?php

namespace App\Services\Offline;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class OfflineInstallationIdentity
{
    public function installationId(): string
    {
        $path = $this->path('installation.json');

        if (File::exists($path)) {
            $data = json_decode((string) File::get($path), true);

            if (is_array($data) && isset($data['installation_id']) && Str::isUuid($data['installation_id'])) {
                return (string) $data['installation_id'];
            }

            throw new RuntimeException('The offline installation identity file is invalid.');
        }

        $this->ensureRoot();
        $installationId = (string) Str::uuid();
        $this->atomicWrite($path, json_encode([
            'installation_id' => $installationId,
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $installationId;
    }

    public function installationSecret(): string
    {
        $path = $this->path('install.key');

        if (File::exists($path)) {
            $decoded = base64_decode(trim((string) File::get($path)), true);

            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }

            throw new RuntimeException('The offline installation secret is invalid.');
        }

        $this->ensureRoot();
        $secret = random_bytes(32);
        $this->atomicWrite($path, base64_encode($secret));

        return $secret;
    }

    public function machineFingerprintHash(): string
    {
        $override = trim((string) config('offline.machine_fingerprint_override'));

        if ($override !== '') {
            return hash('sha256', $override);
        }

        $parts = [
            'os='.PHP_OS_FAMILY,
            'host='.php_uname('n'),
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $machineGuid = $this->run(
                'reg query "HKLM\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid 2>NUL',
            );

            if ($machineGuid !== '') {
                $parts[] = 'machine_guid='.$machineGuid;
            }

            $systemUuid = $this->run(
                'powershell.exe -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_ComputerSystemProduct).UUID" 2>NUL',
            );

            if ($systemUuid !== '') {
                $parts[] = 'system_uuid='.$systemUuid;
            }
        }

        return hash('sha256', implode('|', $parts));
    }

    public function dataRoot(): string
    {
        return rtrim((string) config('offline.data_root'), '\\/');
    }

    private function path(string $filename): string
    {
        return $this->dataRoot().DIRECTORY_SEPARATOR.$filename;
    }

    private function ensureRoot(): void
    {
        File::ensureDirectoryExists($this->dataRoot());
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = $path.'.tmp';
        File::put($temporary, $contents, true);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The offline installation state could not be written.');
        }
    }

    private function run(string $command): string
    {
        if (! function_exists('shell_exec')) {
            return '';
        }

        $output = @shell_exec($command);

        return is_string($output)
            ? preg_replace('/\\s+/', ' ', trim($output)) ?? ''
            : '';
    }
}
