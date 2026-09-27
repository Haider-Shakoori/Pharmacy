<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($user->hasAnyPermission($permissions), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
