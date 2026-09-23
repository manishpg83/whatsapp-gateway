<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\VerifyInternalSecret;
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
        // Where to send visitors who are not logged in / already logged in.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->alias([
            'internal.secret' => VerifyInternalSecret::class,
            'api.token' => AuthenticateApiToken::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // The worker and Cashfree both call these routes directly (no
        // browser session, no CSRF token) — authenticated by the shared
        // secret / webhook signature instead. billing/return is Cashfree's
        // checkout posting the customer's browser back to us, also with
        // no CSRF token of ours.
        $middleware->validateCsrfTokens(except: [
            'internal/worker/events',
            'webhooks/cashfree',
            'billing/return',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
