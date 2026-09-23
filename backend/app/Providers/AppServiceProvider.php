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
    }
}
