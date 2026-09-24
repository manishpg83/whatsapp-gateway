<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No Tailwind in this app (CLAUDE.md §3) — Laravel's pagination
        // views default to Tailwind, so switch to the Bootstrap 5 one.
        Paginator::useBootstrapFive();

        // The public messaging API can be used to spam real phone
        // numbers, so it gets its own (fairly generous, MVP) limit — by
        // token when one was supplied, falling back to IP otherwise.
        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(30)->by($request->bearerToken() ?? $request->ip());
        });

        // Checking a message's status (GET /api/v1/messages/{id}) is
        // read-only and callers may poll it, so it gets a separate, higher
        // limit that never uses up the send limit above.
        RateLimiter::for('message-status', function (Request $request) {
            return Limit::perMinute(60)->by($request->bearerToken() ?? $request->ip());
        });

        // "Send test webhook" makes our server call an owner-supplied URL
        // on demand, so keep it to a handful per minute per user.
        // The public contact form sends a real email each time, so keep
        // it to a few per 10 minutes per visitor (IP) to stop spam floods.
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RateLimiter::for('webhook-test', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
