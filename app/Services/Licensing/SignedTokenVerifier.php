<?php

namespace App\Services\Licensing;

use Illuminate\Auth\AuthenticationException;
use JsonException;

class SignedTokenVerifier
{
    public function verify(
        string $token,
        ?string $purpose = null,
        bool $allowExpired = false,
    ): array {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new AuthenticationException('Token verification is unavailable.');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            throw new AuthenticationException('The mobile access token is invalid.');
        }

        $json = $this->base64UrlDecode($parts[1]);
        $signature = $this->base64UrlDecode($parts[2]);

        $publicKey = base64_decode(
            (string) config('pharmacy.license.signing_public_key'),
            true,
        );

        if ($publicKey === false ||
            strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ||
            strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES ||
            ! sodium_crypto_sign_verify_detached($signature, $json, $publicKey)) {
            throw new AuthenticationException('The mobile access token signature is invalid.');
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AuthenticationException('The mobile access token payload is invalid.');
        }

        if (! is_array($payload)) {
            throw new AuthenticationException('The mobile access token payload is invalid.');
        }

        if ($purpose !== null && ($payload['purpose'] ?? null) !== $purpose) {
            throw new AuthenticationException('The mobile access token purpose is invalid.');
        }

        $expiresAt = filter_var(
            $payload['expires_at'] ?? null,
            FILTER_VALIDATE_INT,
        );

        if ($expiresAt === false) {
            throw new AuthenticationException('The mobile access token expiry is invalid.');
        }

        if (! $allowExpired && $expiresAt <= now()->getTimestamp()) {
            throw new AuthenticationException('The mobile access token has expired.');
        }

        return $payload;
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new AuthenticationException('The mobile access token encoding is invalid.');
        }

        return $decoded;
    }
}
