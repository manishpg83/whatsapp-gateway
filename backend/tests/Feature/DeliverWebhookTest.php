<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DeliverWebhookTest extends TestCase
{
    use RefreshDatabase;

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_it_sends_a_correctly_signed_request(): void
    {
        Http::fake(['https://example.test/webhook' => Http::response('', 200)]);

        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $payload = ['event' => 'message.received', 'message' => 'Hi there'];

        (new DeliverWebhook($instance->id, $payload))->handle();

        Http::assertSent(function ($request) use ($payload) {
            $expectedSignature = 'sha256='.hash_hmac('sha256', $request->body(), 'a-fixed-webhook-secret');

            return $request->url() === 'https://example.test/webhook'
                && $request->header('X-Webhook-Signature')[0] === $expectedSignature
                && json_decode($request->body(), true) === $payload;
        });
    }

    public function test_it_does_nothing_if_the_webhook_was_cleared_before_the_job_ran(): void
    {
        Http::fake();

        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => null,
            'webhook_secret' => null,
        ]);

        (new DeliverWebhook($instance->id, ['event' => 'message.received']))->handle();

        Http::assertNothingSent();
    }

    public function test_it_does_nothing_if_the_instance_no_longer_exists(): void
    {
        Http::fake();

        (new DeliverWebhook(999999, ['event' => 'message.received']))->handle();

        Http::assertNothingSent();
    }

    public function test_a_failed_delivery_throws_so_the_queue_retries_it(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $this->expectException(ConnectionException::class);

        (new DeliverWebhook($instance->id, ['event' => 'message.received']))->handle();
    }

    public function test_failed_logs_a_warning_after_retries_are_exhausted(): void
    {
        Log::spy();

        $job = new DeliverWebhook(1, ['event' => 'message.received']);
        $job->failed(new ConnectionException('Connection refused'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'Webhook delivery failed after all retries');
    }

    public function test_backoff_spreads_retries_out(): void
    {
        $job = new DeliverWebhook(1, []);

        $this->assertSame([10, 60, 300, 900, 1800], $job->backoff());
        $this->assertSame(5, $job->tries);
    }
}
