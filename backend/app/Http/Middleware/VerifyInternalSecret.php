<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the worker->Laravel webhook. Only the Node worker should ever
 * be able to call this route, proven by a shared secret sent as
 * X-Internal-Secret. hash_equals() is PHP's constant-time string
 * comparison, safe against timing attacks even when lengths differ.
 */
class VerifyInternalSecret
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Internal-Secret', '');
        $expected = (string) config('worker.secret');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
