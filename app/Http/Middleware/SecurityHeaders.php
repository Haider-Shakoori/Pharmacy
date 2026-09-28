<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        $forwardedHttps = strtolower(
            (string) $request->header('X-Forwarded-Proto'),
        ) === 'https';

        if (($request->isSecure() || $forwardedHttps) &&
            (bool) config('pharmacy.security.hsts_enabled', true)) {
            $maxAge = (int) config('pharmacy.security.hsts_max_age', 31536000);
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.$maxAge.'; includeSubDomains',
            );
        }

        return $response;
    }
}
