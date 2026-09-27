<?php

namespace App\Http\Middleware;

use App\Services\Settings\PharmacySettings;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantPreferences
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PharmacySettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();
        $previousLocale = App::getLocale();
        $defaultLocale = $this->settings->locale($tenant);
        $locale = (string) $request->session()->get('locale', $defaultLocale);

        if (! in_array($locale, config('pharmacy.locales'), true)) {
            $locale = $defaultLocale;
        }

        App::setLocale($locale);

        try {
            return $next($request);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
