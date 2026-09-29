<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetTenantRouteDefaults
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route === null || ! $route->hasParameter('pharmacy')) {
            return $next($request);
        }

        $pharmacy = (string) $route->parameter('pharmacy');

        URL::defaults(['pharmacy' => $pharmacy]);
        $route->forgetParameter('pharmacy');

        try {
            return $next($request);
        } finally {
            URL::defaults([]);
        }
    }
}