<?php

namespace App\Services\Licensing;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class LicenseKeyService
{
    public function ensureForSubscription(Subscription $subscription): ?string
    {
        if ($subscription->license()->exists()) {
            return null;
        }

        return $this->rotate($subscription);
    }

    public function rotate(Subscription $subscription): string
    {
        return DB::transaction(function () use ($subscription): string {
            $plainText = $this->generatePlainTextKey();
            $hash = hash('sha256', $plainText);
            $hint = substr($plainText, 0, 8).'…'.substr($plainText, -6);

            $license = License::query()->firstOrNew([
                'subscription_id' => $subscription->id,
            ]);

            $license->fill([
                'key_hash' => $hash,
                'key_hint' => $hint,
                'status' => LicenseStatus::Active,
                'version' => $license->exists ? $license->version + 1 : 1,
                'generated_at' => now(),
                'revoked_at' => null,
            ]);
            $license->save();

            $license->activations()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $plainText;
        });
    }

    public function revoke(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $license = $subscription->license()->lockForUpdate()->first();

            if ($license === null) {
                return;
            }

            $license->update([
                'status' => LicenseStatus::Revoked,
                'revoked_at' => now(),
            ]);

            $license->activations()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    public function findByPlainText(string $plainText): ?License
    {
        return License::query()
            ->where('key_hash', hash('sha256', trim($plainText)))
            ->first();
    }

    private function generatePlainTextKey(): string
    {
        $prefix = config('pharmacy.license.key_prefix', 'PHM');

        return $prefix.'-'.strtoupper(bin2hex(random_bytes(24)));
    }
}
