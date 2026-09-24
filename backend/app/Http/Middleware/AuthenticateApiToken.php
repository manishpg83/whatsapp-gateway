<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the public messaging API (CLAUDE.md §6): a Bearer token,
 * looked up by its SHA-256 hash — the plaintext is never stored, so this
 * is the only way to verify one. Attaches the resolved WhatsappSession to
 * the request; controllers use ONLY that, never a session/user-id the
 * caller claims, so a token can never be used to act on an instance it
 * doesn't belong to.
 */
class AuthenticateApiToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $request->bearerToken();

        if (! $plainText) {
            return response()->json(['success' => false, 'error' => 'Missing bearer token'], 401);
        }

        $token = ApiToken::where('token_hash', hash('sha256', $plainText))->first();

        if (! $token || $token->revoked_at) {
            // A revoked token still tells us whose instance was targeted,
            // so LogRejectedApiRequests can show the owner "someone is
            // still using your revoked token". It grants nothing.
            if ($token) {
                $request->attributes->set('revoked_api_token', $token);
            }

            return response()->json(['success' => false, 'error' => 'Invalid or revoked token'], 401);
        }

        $token->update(['last_used_at' => now()]);

        $request->attributes->set('whatsapp_session', $token->whatsappSession);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
