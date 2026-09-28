<?php

namespace App\Services\Mobile;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use JsonException;

class MobileSyncCursor
{
    public function decode(?string $cursor): array
    {
        if ($cursor === null || $cursor === '') {
            return [
                'updated_at' => CarbonImmutable::createFromTimestampUTC(0),
                'id' => '',
            ];
        }

        $raw = $this->decodeBase64Url($cursor);

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid();
        }

        if (! is_array($decoded) ||
            ! is_string($decoded['updated_at'] ?? null) ||
            ! is_string($decoded['id'] ?? null)) {
            throw $this->invalid();
        }

        try {
            $updatedAt = CarbonImmutable::parse($decoded['updated_at'])->utc();
        } catch (\Throwable) {
            throw $this->invalid();
        }

        return [
            'updated_at' => $updatedAt,
            'id' => $decoded['id'],
        ];
    }

    public function encode(object $model): string
    {
        $payload = json_encode([
            'updated_at' => $model->updated_at->toISOString(),
            'id' => (string) $model->id,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function decodeBase64Url(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw $this->invalid();
        }

        return $decoded;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'cursor' => 'The synchronization cursor is invalid.',
        ]);
    }
}
