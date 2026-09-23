<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches a session that was already logged in when an admin suspended the
 * account — LoginController blocks the login itself, but an existing
 * session needs its own check to be kicked out on the very next request.
 */
class EnsureUserIsNotSuspended
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_suspended) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your account has been suspended. Contact support if you believe this is a mistake.');
        }

        return $next($request);
    }
}
