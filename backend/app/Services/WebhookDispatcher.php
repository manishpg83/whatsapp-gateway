<?php

namespace App\Services;

use App\Models\WhatsappSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Forwards an event to the owner's own webhook URL, if they've set one
 * (CLAUDE.md §8/§13 M8). Best-effort: no queue, no retries yet (same MVP
 * stance as the worker calls in M7) — a failure here must never break the
 * thing that triggered it (storing the incoming message), so every
 * failure is caught and logged, never thrown.
 *
 * Known limitation, documented rather than solved here: webhook_url is
 * whatever URL the owner typed in, so this makes a server-side request to
 * an address we don't control (classic SSRF surface). Acceptable for this
 * MVP's threat model (a single beginner-run instance); revisit with an
 * allowlist/egress check before this is ever multi-tenant-at-scale.
 */
class WebhookDispatcher
{
    public function dispatch(WhatsappSession $session, array $payload): void
    {
        if (! $session->webhook_url || ! $session->webhook_secret) {
            return;
        }

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $session->webhook_secret);

        try {
            Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => "sha256={$signature}",
            ])
                ->withBody($body, 'application/json')
                ->timeout(5)
                ->post($session->webhook_url);
        } catch (Throwable $e) {
            Log::warning('Webhook delivery failed', [
                'instance_id' => $session->instance_id,
                'webhook_url' => $session->webhook_url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
