<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Cashfree's Subscriptions API.
 *
 * A subscription's plan_details.plan_id must reference a Plan object that
 * already exists at Cashfree — confirmed by an actual sandbox call (their
 * docs page for Create Subscription doesn't make this clear; sending the
 * rest of the plan fields inline without a real plan_id first returns
 * "Plan does not exist"). So plans are created once, up front, via
 * ensurePlanExists() — safe to call repeatedly, a plan that already
 * exists is left alone.
 */
class CashfreeClient
{
    // Pinned so a future Cashfree API change doesn't silently alter our
    // request/response shape underneath us.
    protected const API_VERSION = '2026-01-01';

    protected function baseUrl(): string
    {
        return config('services.cashfree.env') === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'x-api-version' => self::API_VERSION,
                'x-client-id' => config('services.cashfree.client_id'),
                'x-client-secret' => config('services.cashfree.client_secret'),
            ])
            ->timeout(10);
    }

    /**
     * Creates the Plan object at Cashfree for one of our tiers, if it
     * doesn't already exist there. Idempotent — safe to call before every
     * subscribe attempt, or from a one-off setup command.
     *
     * @param  array{name: string, price: int, cashfree_plan_id: string}  $plan
     */
    public function ensurePlanExists(array $plan): void
    {
        $response = $this->http()->post('/plans', [
            'plan_id' => $plan['cashfree_plan_id'],
            'plan_name' => $plan['name'].' Monthly',
            'plan_type' => 'PERIODIC',
            'plan_currency' => 'INR',
            'plan_recurring_amount' => $plan['price'],
            'plan_max_amount' => $plan['price'],
            'plan_intervals' => 1,
            'plan_interval_type' => 'MONTH',
            'plan_note' => $plan['name'].' plan',
        ]);

        // A 409/"already exists" response is the expected, harmless case
        // on every call after the first — only a genuinely different
        // failure should bubble up.
        if ($response->failed() && ! $this->isAlreadyExists($response)) {
            $response->throw();
        }
    }

    protected function isAlreadyExists(Response $response): bool
    {
        $code = $response->json('code');

        return in_array($code, ['plan_already_exists', 'duplicate_plan_id'], true);
    }

    /**
     * Creates a subscription for one plan and returns Cashfree's response
     * — notably `subscription_session_id`, which the browser uses with
     * Cashfree's JS SDK to open the hosted checkout where the customer
     * actually authorizes the recurring payment.
     *
     * @param  array{name: string, price: int, cashfree_plan_id: string}  $plan
     * @return array{subscription_id: string, cf_subscription_id: string, subscription_session_id: string, subscription_status: string}
     */
    public function createSubscription(
        string $subscriptionId,
        array $plan,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $returnUrl
    ): array {
        $this->ensurePlanExists($plan);

        $response = $this->http()->post('/subscriptions', [
            'subscription_id' => $subscriptionId,
            'customer_details' => [
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
            ],
            'plan_details' => [
                'plan_id' => $plan['cashfree_plan_id'],
            ],
            'authorization_details' => [
                'authorization_amount' => 1,
                'payment_methods' => ['upi', 'card'],
            ],
            'subscription_meta' => [
                'return_url' => $returnUrl,
            ],
        ])->throw();

        return $response->json();
    }
}
