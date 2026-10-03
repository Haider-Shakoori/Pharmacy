<?php

namespace App\Services\Desktop;

use Illuminate\Support\Facades\File;
use RuntimeException;

class DesktopUpdateManifestService
{
    public function manifest(string $channel, string $mode): ?array
    {
        $channel = strtolower(trim($channel));
        $mode = $this->normalizeMode($mode);

        if ($channel !== 'stable') {
            throw new RuntimeException('Unsupported desktop update channel.');
        }

        $root = (string) config(
            'pharmacy.desktop_updates.manifest_directory',
            storage_path('app/private/desktop-updates'),
        );

        $path = rtrim($root, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$channel
            .DIRECTORY_SEPARATOR.$mode.'.json';

        if (!File::isFile($path)) {
            return null;
        }

        $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'schema_version',
            'channel',
            'version',
            'api_version',
            'minimum_supported_version',
            'package_url',
            'package_sha256',
            'published_at',
            'signature',
        ] as $field) {
            if (!array_key_exists($field, $manifest)) {
                throw new RuntimeException("Desktop update manifest is missing {$field}.");
            }
        }

        if ((int) $manifest['schema_version'] !== 1
            || strtolower((string) $manifest['channel']) !== $channel
            || strtolower((string) $manifest['api_version']) !== 'v1'
            || !filter_var($manifest['package_url'], FILTER_VALIDATE_URL)
            || parse_url($manifest['package_url'], PHP_URL_SCHEME) !== 'https'
            || !preg_match('/^[A-Fa-f0-9]{64}$/', (string) $manifest['package_sha256'])
            || !is_string($manifest['signature'])
            || trim($manifest['signature']) === '') {
            throw new RuntimeException('Desktop update manifest failed server-side validation.');
        }

        return $manifest;
    }

    private function normalizeMode(string $mode): string
    {
        return match (strtolower(trim($mode))) {
            'standalone' => 'standalone',
            'server', 'mainserver', 'main-server', 'main_pharmacy_server' => 'server',
            'client', 'clientterminal', 'client-terminal', 'client_terminal' => 'client',
            default => throw new RuntimeException('Unsupported desktop deployment mode.'),
        };
    }
}
