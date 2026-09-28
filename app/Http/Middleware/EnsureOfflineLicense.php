<?php

namespace App\Http\Middleware;

use App\Services\Offline\OfflineLicenseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfflineLicense
{
    public function __construct(
        private readonly OfflineLicenseManager $licenses,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('offline.enabled')) {
            return $next($request);
        }

        if ($request->is('offline/license*') ||
            $request->is('up') ||
            $request->is('ready')) {
            return $next($request);
        }

        $status = $this->licenses->status();

        if ($status['valid']) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Offline license is not operational.',
                'license_status' => $status,
            ], 402);
        }

        return redirect()
            ->route('offline.license.show')
            ->with('warning', $status['message']);
    }
}
