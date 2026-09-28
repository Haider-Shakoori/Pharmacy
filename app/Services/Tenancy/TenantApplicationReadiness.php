<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\Http;

class TenantApplicationReadiness
{
    public function isReady(string $domain): bool
    {
        if (config('pharmacy.provisioning.driver') === 'local') {
            return true;
        }

        try {
            $response = Http::accept('text/html')
                ->timeout((int) config('pharmacy.provisioning.readiness_timeout_seconds', 8))
                ->get('https://'.$domain.'/login');

            return $response->successful() || $response->redirect();
        } catch (\Throwable) {
            return false;
        }
    }
}