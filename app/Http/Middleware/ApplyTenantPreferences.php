<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantPreferences
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();
        $previousLocale = App::getLocale();

        $locale = (string) $request->session()->get('locale', $tenant->locale);

        if (! in_array($locale, config('pharmacy.locales'), true)) {
            $locale = $tenant->locale;
        }

        App::setLocale($locale);

        try {
            return $next($request);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
