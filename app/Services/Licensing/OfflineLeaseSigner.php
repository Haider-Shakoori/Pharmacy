<?php

namespace App\Services\Licensing;

use RuntimeException;

class OfflineLeaseSigner
{
    public function sign(array $payload): string
    {
        if (! function_exists('sodium_crypto_sign_detached')) {
            throw new RuntimeException('The sodium extension is required for offline license signing.');
        }

        $encodedKey = (string) config('pharmacy.license.signing_private_key');
        $secretKey = base64_decode($encodedKey, true);

        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('A valid Ed25519 license signing private key is not configured.');
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = sodium_crypto_sign_detached($json, $secretKey);

        return 'v1.'.$this->base64UrlEncode($json).'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
