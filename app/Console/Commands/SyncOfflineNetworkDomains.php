<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Offline\OfflineNetworkIdentity;
use Illuminate\Console\Command;

class SyncOfflineNetworkDomains extends Command
{
    protected $signature = 'pharmacy:offline:sync-network';

    protected $description = 'Register the current Windows hostname and LAN IPs as valid offline tenant domains.';

    public function handle(OfflineNetworkIdentity $network): int
    {
        if (! config('offline.enabled')) {
            return self::SUCCESS;
        }

        $tenant = Tenant::query()->first();

        if ($tenant === null) {
            $this->error('No offline tenant exists. Run pharmacy:offline:install first.');

            return self::FAILURE;
        }

        foreach ($network->domains() as $domain) {
            $tenant->domains()->firstOrCreate(['domain' => $domain]);
        }

        foreach ($network->urls() as $url) {
            $this->line($url);
        }

        return self::SUCCESS;
    }
}
