<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WhatsappSession;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Forwards an event to the owner's own webhook URL (CLAUDE.md §8/§13 M8),
 * queued so a slow/dead receiver never holds up the request that
 * triggered it (storing the incoming message), and retried with backoff
 * instead of the previous single-attempt-then-give-up behaviour. Each
 * attempt is recorded on its WebhookDelivery row (see WebhookDispatcher).
 *
 * Webhook URLs are owner-typed, so this is a server-side request to an
 * address we don't control (SSRF surface). Mitigated in production by
 * App\Rules\PublicWebhookUrl (no localhost/private addresses, re-checked
 * before every attempt) and by never following redirects.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public int $whatsappSessionId,
        public array $payload,
        public ?int $webhookDeliveryId = null,
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
        $delivery = $this->delivery();

        // The instance (or its webhook) may have been removed/cleared in
        // the time between this job being queued and actually running —
        // nothing to deliver to, not a failure worth retrying.
        if (! $session || ! $session->webhook_url || ! $session->webhook_secret) {
            $delivery?->update(['status' => 'failed', 'error' => 'Webhook URL was removed before delivery']);

            return;
        }

        app(WebhookDispatcher::class)->attempt($session, $this->payload, $delivery);
    }

    /**
     * Called once after all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        $this->delivery()?->update(['status' => 'failed']);

        Log::warning('Webhook delivery failed after all retries', [
            'whatsapp_session_id' => $this->whatsappSessionId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function delivery(): ?WebhookDelivery
    {
        return $this->webhookDeliveryId ? WebhookDelivery::find($this->webhookDeliveryId) : null;
    }
}
