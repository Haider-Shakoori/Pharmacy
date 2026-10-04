<?php

namespace App\Services\Licensing;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\LicenseActivation;
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

            $nextLicenseVersion = $license->exists
                ? $license->version + 1
                : 1;
            $nextWindowsKeyVersion = $license->exists
                ? max(1, (int) $license->windows_activation_key_version + 1)
                : 1;

            $license->fill([
                'key_hash' => $hash,
                'key_hint' => $hint,
                'windows_activation_key_hash' => $hash,
                'windows_activation_key_hint' => $hint,
                'windows_activation_key_version' => $nextWindowsKeyVersion,
                'windows_activation_key_generated_at' => now(),
                'windows_activation_key_consumed_at' => null,
                'windows_activation_id' => null,
                'status' => LicenseStatus::Active,
                'version' => $nextLicenseVersion,
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

    public function reissueWindowsActivationKey(Subscription $subscription): string
    {
        return DB::transaction(function () use ($subscription): string {
            $license = $subscription->license()->lockForUpdate()->first();

            if ($license === null) {
                return $this->rotate($subscription);
            }

            $plainText = $this->generatePlainTextKey();
            $hash = hash('sha256', $plainText);
            $hint = substr($plainText, 0, 8).'…'.substr($plainText, -6);

            $license->forceFill([
                'windows_activation_key_hash' => $hash,
                'windows_activation_key_hint' => $hint,
                'windows_activation_key_version' => max(
                    1,
                    (int) $license->windows_activation_key_version + 1,
                ),
                'windows_activation_key_generated_at' => now(),
                'windows_activation_key_consumed_at' => null,
                'windows_activation_id' => null,
            ])->save();

            $license->activations()
                ->where('platform', 'windows')
                ->whereNull('revoked_at')
                ->get()
                ->each(function (LicenseActivation $activation): void {
                    $activation->forceFill([
                        'revoked_at' => now(),
                        'session_version' => max(1, (int) $activation->session_version + 1),
                        'current_user_id' => null,
                        'current_user_name' => null,
                        'current_user_email' => null,
                        'session_issued_at' => null,
                        'session_expires_at' => null,
                        'last_session_activity_at' => null,
                    ])->save();
                });

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

    public function findForActivation(
        string $plainText,
        string $platform,
    ): ?License {
        if ($platform !== 'windows') {
            return $this->findByPlainText($plainText);
        }

        $hash = hash('sha256', trim($plainText));

        return License::query()
            ->where(function ($query) use ($hash): void {
                $query->where('windows_activation_key_hash', $hash)
                    ->orWhere(function ($query) use ($hash): void {
                        $query->whereNull('windows_activation_key_hash')
                            ->where('key_hash', $hash);
                    });
            })
            ->first();
    }

    private function generatePlainTextKey(): string
    {
        $prefix = config('pharmacy.license.key_prefix', 'PHM');

        return $prefix.'-'.strtoupper(bin2hex(random_bytes(24)));
    }
}
