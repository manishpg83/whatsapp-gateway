<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WhatsappSession;
use App\Rules\PublicWebhookUrl;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Entry point for forwarding an event to the owner's own webhook URL, if
 * they've set one (CLAUDE.md §8/§13 M8). Every delivery gets a
 * WebhookDelivery row so the owner can see on the instance page whether
 * their receiver works.
 *
 * - dispatch(): real events — queued, retried with backoff by DeliverWebhook.
 * - sendTest(): the instance page's "Send test webhook" button — one
 *   immediate attempt, no queue, so it works even if queue:work isn't running.
 * - attempt(): the single signed HTTP request both of the above use.
 */
class WebhookDispatcher
{
    public function dispatch(WhatsappSession $session, array $payload): void
    {
        if (! $session->webhook_url || ! $session->webhook_secret) {
            return;
        }

        $delivery = $session->webhookDeliveries()->create([
            'event' => $payload['event'] ?? 'unknown',
            'url' => $session->webhook_url,
        ]);

        DeliverWebhook::dispatch($session->id, $payload, $delivery->id);
    }

    /**
     * Sends a signed "webhook.test" event right now and returns the
     * delivery row, already marked success or failed.
     */
    public function sendTest(WhatsappSession $session): WebhookDelivery
    {
        $payload = [
            'event' => 'webhook.test',
            'instance_id' => $session->instance_id,
            'message' => 'This is a test webhook from '.config('app.name').'.',
            'timestamp' => now()->timestamp,
        ];

        $delivery = $session->webhookDeliveries()->create([
            'event' => $payload['event'],
            'url' => $session->webhook_url,
        ]);

        try {
            $this->attempt($session, $payload, $delivery);
        } catch (Throwable) {
            $delivery->update(['status' => 'failed']);
        }

        return $delivery->refresh();
    }

    /**
     * One signed POST to the owner's webhook URL. Records the attempt on
     * $delivery (when given) and marks it success; on failure it records
     * the HTTP status / a short error and re-throws, so the queue can
     * retry. Deciding when to give up and mark it "failed" is the caller's job.
     */
    public function attempt(WhatsappSession $session, array $payload, ?WebhookDelivery $delivery = null): void
    {
        $delivery?->increment('attempts');

        try {
            // Re-checked on every attempt, not just when the URL was saved:
            // a hostname's DNS can be changed to point somewhere private later.
            if (! PublicWebhookUrl::isAllowed($session->webhook_url)) {
                throw new RuntimeException('Webhook URL points to localhost or a private network');
            }

            $body = json_encode($payload);
            $signature = hash_hmac('sha256', $body, $session->webhook_secret);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => "sha256={$signature}",
            ])
                ->withBody($body, 'application/json')
                // Never follow redirects: a public URL could otherwise
                // bounce the request to a private address (SSRF).
                ->withoutRedirecting()
                ->timeout(5)
                ->post($session->webhook_url)
                ->throw();

            if ($response->redirect()) {
                $delivery?->update(['response_status' => $response->status()]);

                throw new RuntimeException("Your server replied HTTP {$response->status()} (a redirect) — redirects are not followed, use the final URL");
            }

            $delivery?->update([
                'status' => 'success',
                'response_status' => $response->status(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $delivery?->update(array_filter([
                'response_status' => $e instanceof RequestException ? $e->response->status() : null,
                'error' => $this->shortError($e),
            ]));

            throw $e;
        }
    }

    /**
     * A short, readable error for the owner. For an HTTP error it's just
     * the status code — never the receiver's response body, which Laravel's
     * RequestException message would otherwise include.
     */
    private function shortError(Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return 'Your server replied HTTP '.$e->response->status();
        }

        return Str::limit($e->getMessage(), 250);
    }
}
