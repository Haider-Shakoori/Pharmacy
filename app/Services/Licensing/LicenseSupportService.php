<?php

namespace App\Services\Licensing;

use App\Models\DesktopUserSession;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseSupportAction;
use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\DB;

class LicenseSupportService
{
    public function __construct(
        private readonly OneTimeActivationCodeService $codes,
    ) {}

    public function issueWindowsKey(License $license, ?PlatformAdmin $admin): string
    {
        $plainText = $this->codes->issueWindowsCode($license, $admin);

        $this->audit($license, $admin, 'windows_activation_key_issued');

        return $plainText;
    }

    public function replaceWindowsDevice(
        LicenseActivation $activation,
        ?PlatformAdmin $admin,
    ): string {
        return DB::connection('central')->transaction(function () use ($activation, $admin): string {
            /** @var LicenseActivation $activation */
            $activation = LicenseActivation::query()
                ->with('license.subscription.plan')
                ->lockForUpdate()
                ->findOrFail($activation->id);

            $license = $activation->license;

            $this->codes->revokeUnusedWindowsCodes($license);

            $activation->forceFill(['revoked_at' => now()])->save();
            $activation->desktopSessions()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->audit(
                $license,
                $admin,
                'windows_device_replaced',
                $activation,
            );

            $plainText = $this->codes->issueWindowsCode($license, $admin);

            $this->audit(
                $license,
                $admin,
                'replacement_windows_activation_key_issued',
                $activation,
            );

            return $plainText;
        });
    }

    public function revokeDevice(
        LicenseActivation $activation,
        ?PlatformAdmin $admin,
    ): void {
        DB::connection('central')->transaction(function () use ($activation, $admin): void {
            $activation = LicenseActivation::query()
                ->with('license')
                ->lockForUpdate()
                ->findOrFail($activation->id);

            if ($activation->revoked_at === null) {
                $activation->forceFill(['revoked_at' => now()])->save();
            }

            $activation->desktopSessions()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->audit(
                $activation->license,
                $admin,
                'device_revoked',
                $activation,
            );
        });
    }

    public function forceSignOut(
        DesktopUserSession $session,
        ?PlatformAdmin $admin,
    ): void {
        DB::connection('central')->transaction(function () use ($session, $admin): void {
            $session = DesktopUserSession::query()
                ->with('activation.license')
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->revoked_at === null) {
                $session->forceFill(['revoked_at' => now()])->save();
            }

            $this->audit(
                $session->activation->license,
                $admin,
                'desktop_session_force_signed_out',
                $session->activation,
                $session,
            );
        });
    }

    private function audit(
        License $license,
        ?PlatformAdmin $admin,
        string $action,
        ?LicenseActivation $activation = null,
        ?DesktopUserSession $session = null,
    ): void {
        LicenseSupportAction::query()->create([
            'license_id' => $license->id,
            'license_activation_id' => $activation?->id,
            'desktop_user_session_id' => $session?->id,
            'platform_admin_id' => $admin?->id,
            'action' => $action,
            'metadata' => [
                'device_id' => $activation?->device_id,
                'device_name' => $activation?->device_name,
                'user_email' => $session?->user_email,
            ],
        ]);
    }
}
