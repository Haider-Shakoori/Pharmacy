<?php

namespace App\Services\Offline;

use Illuminate\Support\Facades\File;
use RuntimeException;

class OfflineClockGuard
{
    public function __construct(
        private readonly OfflineInstallationIdentity $identity,
    ) {}

    public function assertCurrentTime(int $licenseIssuedAt): void
    {
        $now = time();
        $tolerance = (int) config('offline.clock_rollback_tolerance_seconds', 300);

        if ($now + $tolerance < $licenseIssuedAt) {
            throw new RuntimeException('The system clock is earlier than the license activation time.');
        }

        $lastSeen = $this->readLastSeen();

        if ($lastSeen !== null && $now + $tolerance < $lastSeen) {
            throw new RuntimeException('The system clock appears to have been moved backwards. Reconnect to the internet and refresh the license.');
        }

        $this->writeLastSeen(max($now, $lastSeen ?? 0));
    }

    public function acceptServerTime(int $timestamp): void
    {
        $this->writeLastSeen(max($timestamp, $this->readLastSeen() ?? 0));
    }

    private function readLastSeen(): ?int
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return null;
        }

        $document = json_decode((string) File::get($path), true);

        if (! is_array($document) ||
            ! isset($document['last_seen'], $document['signature']) ||
            ! is_numeric($document['last_seen']) ||
            ! is_string($document['signature'])) {
            throw new RuntimeException('The trusted clock state is invalid.');
        }

        $lastSeen = (int) $document['last_seen'];
        $expected = hash_hmac('sha256', (string) $lastSeen, $this->identity->installationSecret());

        if (! hash_equals($expected, $document['signature'])) {
            throw new RuntimeException('The trusted clock state was modified.');
        }

        return $lastSeen;
    }

    private function writeLastSeen(int $timestamp): void
    {
        File::ensureDirectoryExists($this->identity->dataRoot());

        $document = json_encode([
            'last_seen' => $timestamp,
            'signature' => hash_hmac('sha256', (string) $timestamp, $this->identity->installationSecret()),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $temporary = $this->path().'.tmp';
        File::put($temporary, $document, true);

        if (! @rename($temporary, $this->path())) {
            @unlink($temporary);
            throw new RuntimeException('The trusted clock state could not be written.');
        }
    }

    private function path(): string
    {
        return $this->identity->dataRoot().DIRECTORY_SEPARATOR.'clock.state';
    }
}
