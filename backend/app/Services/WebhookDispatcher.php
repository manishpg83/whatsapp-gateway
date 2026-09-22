<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\WhatsappSession;

/**
 * Entry point for forwarding an event to the owner's own webhook URL, if
 * they've set one (CLAUDE.md §8/§13 M8). The actual HTTP delivery (with
 * retries/backoff) happens in the queued DeliverWebhook job — this class
 * just decides whether there's anything to deliver to and hands off.
 */
class WebhookDispatcher
{
    public function dispatch(WhatsappSession $session, array $payload): void
    {
        if (! $session->webhook_url || ! $session->webhook_secret) {
            return;
        }

        DeliverWebhook::dispatch($session->id, $payload);
    }
}
