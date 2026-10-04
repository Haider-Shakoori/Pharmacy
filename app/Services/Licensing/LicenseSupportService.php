<?php

namespace App\Services\Licensing;

use App\Models\LicenseActivation;
use App\Models\LicenseSupportAction;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseSupportService
{
    public function __construct(
        private readonly LicenseKeyService $keys,
    ) {}

    public function issueNextKey(
        Subscription $subscription,
        PlatformAdmin $admin,
        ?string $reason = null,
    ): string {
        return DB::transaction(function () use ($subscription, $admin, $reason): string {
            $subscription->loadMissing(['plan', 'license']);

            $activeWindows = $subscription->license?->activations()
                ->where('platform', 'windows')
                ->whereNull('revoked_at')
                ->count() ?? 0;

            $limit = $subscription->plan->max_windows_devices;

            if ($limit !== null && $activeWindows >= $limit) {
                throw ValidationException::withMessages([
                    'license' => 'The Windows device limit has been reached. Release a failed or replaced PC before issuing another activation key.',
                ]);
            }

            $plainText = $this->keys->issueNextWindowsActivationKey($subscription);
            $license = $subscription->license()->firstOrFail();

            $this->audit(
                $license->id,
                null,
                $admin,
                'one_time_key_issued',
                $reason,
                [
                    'generation' => $license->activation_key_generation,
                    'key_hint' => $license->key_hint,
                ],
            );

            return $plainText;
        });
    }

    public function forceSignOut(
        LicenseActivation $activation,
        PlatformAdmin $admin,
        ?string $reason = null,
    ): void {
        DB::transaction(function () use ($activation, $admin, $reason): void {
            $activation = LicenseActivation::query()
                ->with('license')
                ->lockForUpdate()
                ->findOrFail($activation->id);

            if ($activation->platform !== 'windows') {
                throw ValidationException::withMessages([
                    'activation' => 'Only Windows desktop sessions can be force-signed out here.',
                ]);
            }

            $previousUser = $activation->current_user_email;

            $activation->forceFill([
                'session_version' => ((int) $activation->session_version) + 1,
                'current_user_id' => null,
                'current_user_name' => null,
                'current_user_email' => null,
                'session_started_at' => null,
                'session_last_seen_at' => null,
            ])->save();

            $this->audit(
                $activation->license_id,
                $activation->id,
                $admin,
                'session_force_signed_out',
                $reason,
                ['previous_user_email' => $previousUser],
            );
        });
    }

    public function releaseAndReassign(
        LicenseActivation $activation,
        PlatformAdmin $admin,
        ?string $reason = null,
    ): string {
        return DB::transaction(function () use ($activation, $admin, $reason): string {
            $activation = LicenseActivation::query()
                ->with('license.subscription')
                ->lockForUpdate()
                ->findOrFail($activation->id);

            if ($activation->platform !== 'windows') {
                throw ValidationException::withMessages([
                    'activation' => 'Only Windows desktop activations can be released and reassigned here.',
                ]);
            }

            if ($activation->revoked_at === null) {
                $activation->forceFill([
                    'revoked_at' => now(),
                    'session_version' => ((int) $activation->session_version) + 1,
                    'current_user_id' => null,
                    'current_user_name' => null,
                    'current_user_email' => null,
                    'session_started_at' => null,
                    'session_last_seen_at' => null,
                ])->save();
            }

            $plainText = $this->keys->issueNextWindowsActivationKey(
                $activation->license->subscription,
            );

            $activation->license->refresh();

            $this->audit(
                $activation->license_id,
                $activation->id,
                $admin,
                'device_released_and_key_reissued',
                $reason,
                [
                    'device_id' => $activation->device_id,
                    'device_name' => $activation->device_name,
                    'new_generation' => $activation->license->activation_key_generation,
                    'new_key_hint' => $activation->license->key_hint,
                ],
            );

            return $plainText;
        });
    }

    private function audit(
        string $licenseId,
        ?string $activationId,
        PlatformAdmin $admin,
        string $action,
        ?string $reason,
        array $metadata = [],
    ): void {
        LicenseSupportAction::query()->create([
            'license_id' => $licenseId,
            'activation_id' => $activationId,
            'platform_admin_id' => $admin->id,
            'action' => $action,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
