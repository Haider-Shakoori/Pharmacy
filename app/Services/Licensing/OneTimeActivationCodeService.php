<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseActivationCode;
use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OneTimeActivationCodeService
{
    public function issueWindowsCode(License $license, ?PlatformAdmin $admin = null): string
    {
        return DB::connection('central')->transaction(function () use ($license, $admin): string {
            /** @var License $license */
            $license = License::query()
                ->with('subscription.plan')
                ->lockForUpdate()
                ->findOrFail($license->id);

            $plan = $license->subscription->plan;
            $activeWindows = $license->activations()
                ->where('platform', 'windows')
                ->whereNull('revoked_at')
                ->count();

            $unusedCodes = $license->activationCodes()
                ->where('platform', 'windows')
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->count();

            if ($plan->max_windows_devices !== null
                && ($activeWindows + $unusedCodes) >= $plan->max_windows_devices) {
                throw ValidationException::withMessages([
                    'license' => 'No unused Windows activation slot is available. Revoke or replace an existing device first.',
                ]);
            }

            $plainText = $this->generatePlainTextKey();

            LicenseActivationCode::query()->create([
                'license_id' => $license->id,
                'key_hash' => hash('sha256', $plainText),
                'key_hint' => substr($plainText, 0, 12).'…'.substr($plainText, -6),
                'platform' => 'windows',
                'issued_by_platform_admin_id' => $admin?->id,
                'generated_at' => now(),
            ]);

            return $plainText;
        });
    }

    public function revokeUnusedWindowsCodes(License $license): void
    {
        $license->activationCodes()
            ->where('platform', 'windows')
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function findByPlainText(string $plainText): ?LicenseActivationCode
    {
        return LicenseActivationCode::query()
            ->where('key_hash', hash('sha256', trim($plainText)))
            ->first();
    }

    private function generatePlainTextKey(): string
    {
        return 'PHM-WIN-'.strtoupper(bin2hex(random_bytes(24)));
    }
}
