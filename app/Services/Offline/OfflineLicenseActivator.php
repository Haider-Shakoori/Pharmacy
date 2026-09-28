<?php

namespace App\Services\Offline;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OfflineLicenseActivator
{
    public function __construct(
        private readonly OfflineInstallationIdentity $identity,
        private readonly OfflineLicenseStore $store,
        private readonly OfflineLicenseManager $manager,
        private readonly OfflineClockGuard $clock,
    ) {}

    public function activate(string $licenseKey): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('offline.http_timeout_seconds', 20))
            ->post((string) config('offline.activation_url'), [
                'license_key' => trim($licenseKey),
                'installation_id' => $this->identity->installationId(),
                'machine_fingerprint_hash' => $this->identity->machineFingerprintHash(),
                'device_name' => php_uname('n'),
                'app_version' => (string) config('offline.app_version', 'dev'),
            ]);

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('errors.license_key.0')
                ?? $response->json('errors.installation_id.0')
                ?? 'BusinessOS could not activate this offline installation.';

            throw ValidationException::withMessages([
                'license_key' => $message,
            ]);
        }

        $token = $response->json('data.license_token');
        $serverTime = $response->json('server_time');

        if (! is_string($token) || $token === '' || ! is_string($serverTime)) {
            throw new RuntimeException('The activation server returned an invalid response.');
        }

        $serverTimestamp = CarbonImmutable::parse($serverTime)->getTimestamp();
        $this->clock->acceptServerTime($serverTimestamp);
        $payload = $this->manager->validateToken($token);
        $this->store->write($token);

        return $payload;
    }
}
