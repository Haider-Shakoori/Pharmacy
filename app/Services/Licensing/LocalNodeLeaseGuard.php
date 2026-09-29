<?php

namespace App\Services\Licensing;

use App\Models\LocalNodeState;
use App\Models\Tenant;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LocalNodeLeaseGuard
{
    public function __construct(
        private readonly SignedTokenVerifier $tokens,
    ) {}

    public function assertOperational(Tenant $tenant): void
    {
        $state = LocalNodeState::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->first();

        if ($state === null) {
            throw new HttpException(402, 'The local pharmacy license has not been activated.');
        }

        try {
            $payload = $this->tokens->verifyWithPublicKey(
                $state->lease_token,
                $state->lease_public_key,
            );
        } catch (AuthenticationException $exception) {
            throw new HttpException(402, $exception->getMessage(), $exception);
        }

        if ((string) ($payload['tenant_id'] ?? '') !== (string) $tenant->getTenantKey()) {
            throw new HttpException(402, 'The offline license belongs to a different pharmacy.');
        }

        if ($state->device_id !== null &&
            (string) ($payload['device_id'] ?? '') !== (string) $state->device_id) {
            throw new HttpException(402, 'The offline license belongs to a different Windows installation.');
        }

        $now = now();
        $skew = (int) config('pharmacy.security.token_clock_skew_seconds', 300);

        if ($now->getTimestamp() + $skew < $state->last_observed_at->getTimestamp()) {
            throw new HttpException(
                402,
                'The Windows clock moved backwards. Correct the clock and reconnect to BusinessOS licensing.',
            );
        }

        $signedExpiresAt = filter_var(
            $payload['expires_at'] ?? null,
            FILTER_VALIDATE_INT,
        );

        if ($signedExpiresAt === false ||
            $signedExpiresAt !== $state->lease_expires_at->getTimestamp()) {
            throw new HttpException(402, 'The stored offline license expiry is invalid.');
        }

        if ($signedExpiresAt <= $now->getTimestamp()) {
            throw new HttpException(
                402,
                'The offline license period has expired. Connect to the internet and renew the BusinessOS license.',
            );
        }

        if ($now->greaterThan($state->last_observed_at)) {
            $state->forceFill(['last_observed_at' => $now])->save();
            $this->writeHighWaterMark($now->getTimestamp());
        }

        $fileMark = $this->readHighWaterMark();
        if ($fileMark !== null && $now->getTimestamp() + $skew < $fileMark) {
            throw new HttpException(
                402,
                'The Windows clock moved backwards. Correct the clock and reconnect to BusinessOS licensing.',
            );
        }
    }

    public function recordHighWaterMark(int $timestamp): void
    {
        $this->writeHighWaterMark($timestamp);
    }

    private function readHighWaterMark(): ?int
    {
        $path = (string) config('pharmacy.local_node.clock_state_file');
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $value = trim((string) @file_get_contents($path));

        return ctype_digit($value) ? (int) $value : null;
    }

    private function writeHighWaterMark(int $timestamp): void
    {
        $path = (string) config('pharmacy.local_node.clock_state_file');
        if ($path === '') {
            return;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $current = $this->readHighWaterMark();
        $value = max($timestamp, $current ?? 0);
        @file_put_contents($path, (string) $value, LOCK_EX);
    }
}