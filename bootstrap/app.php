<?php

use App\Http\Middleware\EnsureOperationalSubscription;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'subscription.operational' => EnsureOperationalSubscription::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('platform*')
                ? route('platform.login')
                : route('pharmacy.login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('platform*')
                ? route('platform.dashboard')
                : route('pharmacy.dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
