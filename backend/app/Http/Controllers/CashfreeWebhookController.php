<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives subscription lifecycle events from Cashfree (activated,
 * cancelled, payment failed, etc). Verified per Cashfree's own docs
 * (https://www.cashfree.com/docs/payments/online/webhooks/signature-verification):
 * base64(HMAC-SHA256(x-webhook-timestamp header + RAW request body,
 * our client secret)), compared to the x-webhook-signature header.
 *
 * The exact event envelope (which JSON keys hold the subscription id and
 * status) isn't 100% pinned down from docs alone — logged in full so real
 * sandbox payloads can be inspected and the field lookups adjusted once
 * we see one, same as the worker's Baileys integration needed live
 * iteration. Never 500s on an unrecognised shape — that would make
 * Cashfree retry-storm us.
 */
class CashfreeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Cashfree webhook: invalid or missing signature');

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();

        Log::info('Cashfree webhook received', [
            'type' => $payload['type'] ?? null,
            'payload' => $payload,
        ]);

        $subscriptionId = data_get($payload, 'data.subscription.subscription_id')
            ?? data_get($payload, 'data.subscription_id');

        if (! $subscriptionId) {
            return response()->noContent();
        }

        $subscription = Subscription::where('cashfree_subscription_id', $subscriptionId)->first();

        if (! $subscription) {
            Log::warning('Cashfree webhook for an unknown subscription_id', ['subscription_id' => $subscriptionId]);

            return response()->noContent();
        }

        $status = data_get($payload, 'data.subscription.subscription_status')
            ?? data_get($payload, 'data.subscription_status');

        if ($status) {
            $subscription->update(['status' => $this->mapStatus($status)]);
        }

        return response()->noContent();
    }

    /**
     * Cashfree signs `timestamp + rawBody`, HMAC-SHA256 with the client
     * secret, base64-encoded. Must use the RAW body — Cashfree's own docs
     * are explicit that a re-serialized/parsed body produces a different
     * signature and won't match.
     */
    protected function hasValidSignature(Request $request): bool
    {
        $timestamp = $request->header('x-webhook-timestamp');
        $signature = $request->header('x-webhook-signature');

        if (! $timestamp || ! $signature) {
            return false;
        }

        // Defence-in-depth against replay: reject anything not roughly
        // "now" (Cashfree doesn't document a tolerance, 5 minutes is a
        // reasonable default for a webhook, not a guess at their spec).
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $timestamp.$request->getContent(),
            (string) config('services.cashfree.client_secret'),
            true
        ));

        return hash_equals($expected, $signature);
    }

    protected function mapStatus(string $cashfreeStatus): string
    {
        return match (strtoupper($cashfreeStatus)) {
            'ACTIVE' => 'active',
            'CANCELLED', 'CANCELED' => 'cancelled',
            'ON_HOLD', 'PAUSED' => 'past_due',
            default => 'pending',
        };
    }
}
