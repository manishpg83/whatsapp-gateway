<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the /admin section to accounts with is_admin = true. A 404, not a
 * 403 — matches this app's existing convention (see InstanceController's
 * ownership checks) of not confirming a protected area even exists to
 * someone who shouldn't be able to see it.
 */
class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->is_admin, 404);

        return $next($request);
    }
}
