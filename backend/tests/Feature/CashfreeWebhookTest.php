<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SubscriptionActivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CashfreeWebhookTest extends TestCase
{
    use RefreshDatabase;

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    /**
     * Posts a genuinely well-signed webhook request, built the same way
     * the controller verifies it — so tests exercise real signature
     * matching, not a bypass.
     */
    private function postSignedWebhook(array $payload, array $headerOverrides = [], ?int $timestamp = null): TestResponse
    {
        $rawBody = json_encode($payload);
        $timestamp ??= time();
        $secret = config('services.cashfree.client_secret');

        $signature = base64_encode(hash_hmac('sha256', $timestamp.$rawBody, $secret, true));

        $headers = array_merge([
            'x-webhook-timestamp' => (string) $timestamp,
            'x-webhook-signature' => $signature,
        ], $headerOverrides);

        $serverHeaders = collect($headers)
            ->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])
            ->all();

        return $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            ...$serverHeaders,
        ], $rawBody);
    }

    public function test_rejects_a_request_with_no_signature(): void
    {
        $this->postJson('/webhooks/cashfree', ['type' => 'x'])->assertUnauthorized();
    }

    public function test_rejects_a_request_with_a_wrong_signature(): void
    {
        $this->postSignedWebhook(
            ['type' => 'SUBSCRIPTION_STATUS_CHANGE', 'data' => ['subscription_id' => 'sub_x']],
            ['x-webhook-signature' => 'not-the-real-signature']
        )->assertUnauthorized();
    }

    public function test_rejects_a_stale_timestamp(): void
    {
        $this->postSignedWebhook(
            ['type' => 'SUBSCRIPTION_STATUS_CHANGE', 'data' => ['subscription_id' => 'sub_x']],
            timestamp: time() - 600 // 10 minutes old
        )->assertUnauthorized();
    }

    public function test_valid_signature_updates_the_subscription_status(): void
    {
        $user = User::factory()->create();
        $user->subscription->update([
            'plan' => 'starter',
            'status' => 'pending',
            'cashfree_subscription_id' => 'sub_test_123',
        ]);

        $this->postSignedWebhook([
            'type' => 'SUBSCRIPTION_STATUS_CHANGE',
            'data' => [
                'subscription' => [
                    'subscription_id' => 'sub_test_123',
                    'subscription_status' => 'ACTIVE',
                ],
            ],
        ])->assertNoContent();

        $this->assertSame('active', $user->subscription->fresh()->status);
    }

    private function statusWebhook(string $subscriptionId, string $status): TestResponse
    {
        return $this->postSignedWebhook([
            'type' => 'SUBSCRIPTION_STATUS_CHANGE',
            'data' => ['subscription' => ['subscription_id' => $subscriptionId, 'subscription_status' => $status]],
        ]);
    }

    private function userWithSubscription(string $plan, string $status): User
    {
        $user = User::factory()->create();
        $user->subscription->update(['plan' => $plan, 'status' => $status, 'cashfree_subscription_id' => 'sub_mail_1']);

        return $user;
    }

    public function test_new_purchase_sends_the_subscribed_email_once(): void
    {
        Notification::fake();
        $user = $this->userWithSubscription('growth', 'pending');

        $this->statusWebhook('sub_mail_1', 'ACTIVE')->assertNoContent();
        $this->statusWebhook('sub_mail_1', 'ACTIVE'); // retry / renewal while already active

        Notification::assertSentToTimes($user, SubscriptionActivated::class, 1);
    }

    public function test_recovering_from_a_failed_payment_does_not_resend_it(): void
    {
        Notification::fake();
        $user = $this->userWithSubscription('growth', 'past_due');

        $this->statusWebhook('sub_mail_1', 'ACTIVE');

        Notification::assertNotSentTo($user, SubscriptionActivated::class);
    }

    public function test_subscribed_email_content(): void
    {
        $user = $this->userWithSubscription('growth', 'active');
        $user->subscription->update(['current_period_end' => '2026-10-26']);
        $mail = (new SubscriptionActivated($user->subscription->fresh()))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame("You're subscribed to the Growth plan", $mail->subject);
        $this->assertStringContainsString('₹1,499 / month', $html);
        $this->assertStringContainsString('October 26, 2026', $html);
        $this->assertStringContainsString(route('billing.index'), $html);
    }

    public function test_unknown_subscription_id_is_ignored_without_error(): void
    {
        $this->postSignedWebhook([
            'type' => 'SUBSCRIPTION_STATUS_CHANGE',
            'data' => ['subscription' => ['subscription_id' => 'sub_does_not_exist', 'subscription_status' => 'ACTIVE']],
        ])->assertNoContent();
    }

    public function test_cancelled_status_is_mapped_correctly(): void
    {
        $user = User::factory()->create();
        $user->subscription->update([
            'plan' => 'starter',
            'status' => 'active',
            'cashfree_subscription_id' => 'sub_test_456',
        ]);

        $this->postSignedWebhook([
            'type' => 'SUBSCRIPTION_STATUS_CHANGE',
            'data' => [
                'subscription' => [
                    'subscription_id' => 'sub_test_456',
                    'subscription_status' => 'CANCELLED',
                ],
            ],
        ])->assertNoContent();

        $this->assertSame('cancelled', $user->subscription->fresh()->status);
    }

    private function paymentEvent(string $type, string $cfPaymentId = '49914526', string $status = 'SUCCESS'): array
    {
        return [
            'type' => $type,
            'event_time' => '2026-09-30T10:30:00+05:30',
            'data' => [
                'payment_id' => 'pay-'.$cfPaymentId,
                'cf_payment_id' => $cfPaymentId,
                'payment_amount' => 749.00,
                'payment_currency' => 'INR',
                'payment_status' => $status,
                'subscription_id' => 'sub_pay_1',
            ],
        ];
    }

    private function paidUser(): User
    {
        $user = User::factory()->create();
        $user->subscription->update([
            'plan' => 'starter',
            'status' => 'active',
            'cashfree_subscription_id' => 'sub_pay_1',
        ]);

        return $user;
    }

    public function test_payment_success_event_is_saved_to_payment_history(): void
    {
        $user = $this->paidUser();

        $this->postSignedWebhook($this->paymentEvent('SUBSCRIPTION_PAYMENT_SUCCESS'))->assertNoContent();

        $payment = $user->payments()->sole();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('749.00', $payment->amount);
        $this->assertSame('INR', $payment->currency);
        $this->assertSame('Starter', $payment->plan_name);
        $this->assertSame('sub_pay_1', $payment->cashfree_subscription_id);
        // 10:30 IST stored as 05:00 UTC.
        $this->assertSame('2026-09-30 05:00:00', $payment->paid_at->format('Y-m-d H:i:s'));
    }

    public function test_a_retried_payment_webhook_does_not_create_a_duplicate(): void
    {
        $user = $this->paidUser();

        $this->postSignedWebhook($this->paymentEvent('SUBSCRIPTION_PAYMENT_SUCCESS'))->assertNoContent();
        $this->postSignedWebhook($this->paymentEvent('SUBSCRIPTION_PAYMENT_SUCCESS'))->assertNoContent();

        $this->assertSame(1, $user->payments()->count());
    }

    public function test_payment_failed_event_is_saved_as_failed(): void
    {
        $user = $this->paidUser();

        $this->postSignedWebhook($this->paymentEvent('SUBSCRIPTION_PAYMENT_FAILED', '777', 'FAILED'))->assertNoContent();

        $this->assertSame('failed', $user->payments()->sole()->status);
    }

    public function test_non_payment_events_do_not_create_payments(): void
    {
        $user = $this->paidUser();

        $this->postSignedWebhook($this->paymentEvent('SUBSCRIPTION_PAYMENT_NOTIFICATION_INITIATED'))->assertNoContent();

        $this->assertSame(0, $user->payments()->count());
    }
}
