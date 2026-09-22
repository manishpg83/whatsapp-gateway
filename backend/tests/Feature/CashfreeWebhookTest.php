<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
