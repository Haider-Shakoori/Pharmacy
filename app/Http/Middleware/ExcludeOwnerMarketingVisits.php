<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExcludeOwnerMarketingVisits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Admin-authenticated browser opt-out shared with the public apex site. HTTP-only,
        // so site visitors cannot impersonate the admin using this cookie for access.
        return $response->withCookie(cookie(
            'dt_analytics_exclude', '1', 525600, '/', '.darmaltoon.com', true, true, false, 'lax',
        ));
    }
}
