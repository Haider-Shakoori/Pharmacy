<?php

namespace App\Services\Offline;

use App\Services\Licensing\SignedTokenVerifier;
use RuntimeException;
use Throwable;

class OfflineLicenseManager
{
    public function __construct(
        private readonly OfflineLicenseStore $store,
        private readonly OfflineInstallationIdentity $identity,
        private readonly OfflineClockGuard $clock,
        private readonly SignedTokenVerifier $verifier,
    ) {}

    public function assertValid(): array
    {
        return $this->validateToken($this->store->read());
    }

    public function validateToken(string $token): array
    {
        $payload = $this->verifier->verify($token, 'offline_installation');

        if (($payload['product'] ?? null) !== 'businessos-pharmacy' ||
            ($payload['edition'] ?? null) !== 'offline') {
            throw new RuntimeException('The license is not valid for BusinessOS Pharmacy Offline.');
        }

        if (($payload['installation_id'] ?? null) !== $this->identity->installationId()) {
            throw new RuntimeException('The license belongs to another installation.');
        }

        $fingerprint = (string) ($payload['machine_fingerprint_hash'] ?? '');

        if ($fingerprint === '' || ! hash_equals($fingerprint, $this->identity->machineFingerprintHash())) {
            throw new RuntimeException('The license belongs to another computer.');
        }

        $issuedAt = filter_var($payload['issued_at'] ?? null, FILTER_VALIDATE_INT);

        if ($issuedAt === false) {
            throw new RuntimeException('The offline license issue time is invalid.');
        }

        $this->clock->assertCurrentTime($issuedAt);

        return $payload;
    }

    public function status(): array
    {
        try {
            $payload = $this->assertValid();

            return [
                'valid' => true,
                'message' => 'License active.',
                'payload' => $payload,
                'license_path' => $this->store->path(),
            ];
        } catch (Throwable $exception) {
            return [
                'valid' => false,
                'message' => $exception->getMessage(),
                'payload' => null,
                'license_path' => $this->store->path(),
            ];
        }
    }
}
