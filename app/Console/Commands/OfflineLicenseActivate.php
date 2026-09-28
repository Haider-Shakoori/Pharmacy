<?php

namespace App\Console\Commands;

use App\Services\Offline\OfflineLicenseActivator;
use Illuminate\Console\Command;
use Throwable;

class OfflineLicenseActivate extends Command
{
    protected $signature = 'pharmacy:offline:activate {license_key? : BusinessOS offline license key}';

    protected $description = 'Activate or renew this offline Pharmacy installation over the internet.';

    public function handle(OfflineLicenseActivator $activator): int
    {
        $licenseKey = (string) ($this->argument('license_key') ?: $this->secret('Offline license key'));

        if (trim($licenseKey) === '') {
            $this->error('A license key is required.');

            return self::FAILURE;
        }

        try {
            $payload = $activator->activate($licenseKey);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('BusinessOS Pharmacy Offline activated.');
        $this->line('Pharmacy: '.($payload['pharmacy_name'] ?? '—'));
        $this->line('Expires: '.date('c', (int) $payload['expires_at']));

        return self::SUCCESS;
    }
}
