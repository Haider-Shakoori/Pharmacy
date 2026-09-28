<?php

namespace App\Console\Commands;

use App\Services\Offline\OfflineInstallationIdentity;
use App\Services\Offline\OfflineLicenseManager;
use Illuminate\Console\Command;

class OfflineLicenseStatus extends Command
{
    protected $signature = 'pharmacy:offline:license-status';

    protected $description = 'Show the local BusinessOS Pharmacy Offline license status.';

    public function handle(
        OfflineLicenseManager $licenses,
        OfflineInstallationIdentity $identity,
    ): int {
        $status = $licenses->status();

        $this->table(['Field', 'Value'], [
            ['Edition', config('offline.enabled') ? 'offline' : 'cloud'],
            ['Installation ID', $identity->installationId()],
            ['Machine fingerprint', $identity->machineFingerprintHash()],
            ['License valid', $status['valid'] ? 'yes' : 'no'],
            ['Status', $status['message']],
            ['License file', $status['license_path']],
            ['Pharmacy', $status['payload']['pharmacy_name'] ?? '—'],
            ['Plan', $status['payload']['plan_code'] ?? '—'],
            ['Expires', isset($status['payload']['expires_at']) ? date('c', (int) $status['payload']['expires_at']) : '—'],
        ]);

        return $status['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
