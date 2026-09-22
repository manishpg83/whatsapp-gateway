<?php

namespace App\Jobs;

use App\Models\WhatsappSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Forwards an event to the owner's own webhook URL (CLAUDE.md §8/§13 M8),
 * queued so a slow/dead receiver never holds up the request that
 * triggered it (storing the incoming message), and retried with backoff
 * instead of the previous single-attempt-then-give-up behaviour.
 *
 * Known limitation, documented rather than solved here: webhook_url is
 * whatever URL the owner typed in, so this makes a server-side request to
 * an address we don't control (classic SSRF surface). Acceptable for this
 * MVP's threat model (a single beginner-run instance); revisit with an
 * allowlist/egress check before this is ever multi-tenant-at-scale.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public int $whatsappSessionId,
        public array $payload,
    ) {}

    /**
     * Spread retries out instead of hammering a receiver that's only
     * briefly down: 10s, 1m, 5m, 15m, 30m.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 1800];
    }

    public function handle(): void
    {
        $session = WhatsappSession::find($this->whatsappSessionId);

        // The instance (or its webhook) may have been removed/cleared in
        // the time between this job being queued and actually running —
        // nothing to deliver to, not a failure.
        if (! $session || ! $session->webhook_url || ! $session->webhook_secret) {
            return;
        }

        $body = json_encode($this->payload);
        $signature = hash_hmac('sha256', $body, $session->webhook_secret);

        Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => "sha256={$signature}",
        ])
            ->withBody($body, 'application/json')
            ->timeout(5)
            ->post($session->webhook_url)
            ->throw();
    }

    /**
     * Called once after all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('Webhook delivery failed after all retries', [
            'whatsapp_session_id' => $this->whatsappSessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
