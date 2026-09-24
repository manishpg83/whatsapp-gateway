<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records public-API calls that were rejected (any 4xx/5xx response), so
 * they show up under API Logs → "Rejected requests". Runs OUTSIDE the
 * api.token middleware (listed first on the route), so it also sees 401s.
 *
 * Skipped when:
 * - no token identifies an instance (unknown/missing token) — there's no
 *   owner to show it to, and logging it would let anyone fill the table;
 * - the call already created a message row (e.g. the worker failed to
 *   send) — that one is already listed under "API calls".
 */
class LogRejectedApiRequests
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400 && ! $request->attributes->get('api_message_created')) {
            $this->record($request, $response);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        /** @var ApiToken|null $token set by AuthenticateApiToken (a valid or a revoked token) */
        $token = $request->attributes->get('api_token') ?? $request->attributes->get('revoked_api_token');

        if (! $token) {
            return;
        }

        try {
            ApiRequestLog::create([
                'whatsapp_session_id' => $token->whatsapp_session_id,
                'api_token_id' => $token->id,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status_code' => $response->getStatusCode(),
                'error' => $this->errorMessage($response),
                'to_number' => is_string($request->input('to')) ? Str::limit($request->input('to'), 30, '') : null,
                'type' => is_string($request->input('type')) ? Str::limit($request->input('type'), 20, '') : null,
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break the API response itself.
            Log::warning('Could not record a rejected API request', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The same error text the caller received: our {"error": "..."}, or
     * Laravel's validation errors ("to: The to field format is invalid."),
     * or its generic "message".
     */
    private function errorMessage(Response $response): ?string
    {
        if (! $response instanceof JsonResponse) {
            return null;
        }

        $data = $response->getData(true);

        if (! empty($data['errors']) && is_array($data['errors'])) {
            $parts = [];
            foreach ($data['errors'] as $field => $messages) {
                $parts[] = "{$field}: ".(is_array($messages) ? ($messages[0] ?? '') : $messages);
            }

            return Str::limit(implode(' · ', $parts), 250);
        }

        $message = $data['error'] ?? $data['message'] ?? null;

        return is_string($message) ? Str::limit($message, 250) : null;
    }
}
