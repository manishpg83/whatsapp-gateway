<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\SubscriptionActivated;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $this->recordPayment($subscription, $payload);

        $status = data_get($payload, 'data.subscription.subscription_status')
            ?? data_get($payload, 'data.subscription_status');

        if ($status) {
            $previousStatus = $subscription->status;
            $subscription->update(['status' => $this->mapStatus($status)]);

            // A new purchase: pending -> active. Retries and monthly
            // renewals arrive while it's already active, and a recovered
            // failed payment comes from past_due, so neither emails again.
            if ($previousStatus === 'pending' && $subscription->status === 'active') {
                $this->sendSubscribedEmail($subscription);
            }
        }

        return response()->noContent();
    }

    /**
     * Saves a SUBSCRIPTION_PAYMENT_SUCCESS / _FAILED / _CANCELLED event as
     * a row in the user's payment history. Field names follow Cashfree's
     * documented payload (data.cf_payment_id, data.payment_amount, ...).
     * Keyed on cf_payment_id, so a retried webhook updates the same row
     * instead of adding a duplicate.
     */
    protected function recordPayment(Subscription $subscription, array $payload): void
    {
        $status = match ($payload['type'] ?? null) {
            'SUBSCRIPTION_PAYMENT_SUCCESS' => 'paid',
            'SUBSCRIPTION_PAYMENT_FAILED' => 'failed',
            'SUBSCRIPTION_PAYMENT_CANCELLED' => 'cancelled',
            default => null,
        };

        $paymentId = data_get($payload, 'data.cf_payment_id') ?? data_get($payload, 'data.payment_id');

        if (! $status || ! $paymentId) {
            return;
        }

        $plan = $subscription->planDetails();

        // Cashfree sends IST with an offset ("...+05:30"); convert to the
        // app's timezone, or the database would store the IST clock time.
        $eventTime = data_get($payload, 'event_time');
        $paidAt = $eventTime ? Carbon::parse($eventTime)->setTimezone(config('app.timezone')) : now();

        Payment::updateOrCreate(
            ['cf_payment_id' => (string) $paymentId],
            [
                'user_id' => $subscription->user_id,
                'plan' => $subscription->plan,
                'plan_name' => $plan['name'],
                'amount' => data_get($payload, 'data.payment_amount') ?? $plan['price'],
                'currency' => data_get($payload, 'data.payment_currency') ?? 'INR',
                'status' => $status,
                'cashfree_subscription_id' => $subscription->cashfree_subscription_id,
                'paid_at' => $paidAt,
            ]
        );
    }

    protected function sendSubscribedEmail(Subscription $subscription): void
    {
        if ($subscription->planDetails()['price'] <= 0) {
            return;
        }

        try {
            $subscription->user->notify(new SubscriptionActivated($subscription));
        } catch (Throwable $e) {
            // Never fail the webhook over an email — Cashfree would retry it.
            Log::warning('Could not send subscription confirmation email', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
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
