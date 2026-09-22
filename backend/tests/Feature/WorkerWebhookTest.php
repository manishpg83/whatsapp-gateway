<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-shared-secret-value-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic secret for these tests, independent of whatever's
        // in the real .env.
        config(['worker.secret' => self::SECRET]);
    }

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_qr_updated_event_updates_the_instance(): void
    {
        $instance = WhatsappSession::factory()->create(['status' => 'connecting']);

        $response = $this->postJson('/internal/worker/events', [
            'event' => 'qr.updated',
            'instance_id' => $instance->instance_id,
            'qr_code' => 'data:image/png;base64,fakeqrdata',
        ], ['X-Internal-Secret' => self::SECRET]);

        $response->assertNoContent();

        $instance->refresh();
        $this->assertSame('qr_pending', $instance->status);
        $this->assertSame('data:image/png;base64,fakeqrdata', $instance->qr_code);
        $this->assertNotNull($instance->qr_updated_at);
    }

    public function test_connection_updated_event_marks_the_instance_connected(): void
    {
        $instance = WhatsappSession::factory()->create([
            'status' => 'qr_pending',
            'qr_code' => 'data:image/png;base64,stale',
        ]);

        $response = $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'connected',
            'phone_number' => '919999999999',
        ], ['X-Internal-Secret' => self::SECRET]);

        $response->assertNoContent();

        $instance->refresh();
        $this->assertSame('connected', $instance->status);
        $this->assertSame('919999999999', $instance->phone_number);
        $this->assertNotNull($instance->connected_at);
        $this->assertNull($instance->qr_code);
    }

    public function test_connection_updated_event_marks_the_instance_logged_out(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $response = $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'logged_out',
            'last_disconnect_reason' => 'Device removed from phone',
        ], ['X-Internal-Secret' => self::SECRET]);

        $response->assertNoContent();

        $instance->refresh();
        $this->assertSame('logged_out', $instance->status);
        $this->assertSame('Device removed from phone', $instance->last_disconnect_reason);
    }

    public function test_webhook_rejects_a_missing_secret(): void
    {
        $instance = WhatsappSession::factory()->create(['status' => 'connecting']);

        $this->postJson('/internal/worker/events', [
            'event' => 'qr.updated',
            'instance_id' => $instance->instance_id,
            'qr_code' => 'data:image/png;base64,x',
        ])->assertUnauthorized();

        $this->assertSame('connecting', $instance->fresh()->status);
    }

    public function test_webhook_rejects_the_wrong_secret(): void
    {
        $instance = WhatsappSession::factory()->create(['status' => 'connecting']);

        $this->postJson('/internal/worker/events', [
            'event' => 'qr.updated',
            'instance_id' => $instance->instance_id,
            'qr_code' => 'data:image/png;base64,x',
        ], ['X-Internal-Secret' => 'wrong-secret'])->assertUnauthorized();

        $this->assertSame('connecting', $instance->fresh()->status);
    }

    public function test_webhook_404s_for_an_unknown_instance_id(): void
    {
        $this->postJson('/internal/worker/events', [
            'event' => 'qr.updated',
            'instance_id' => (string) Str::uuid(),
            'qr_code' => 'data:image/png;base64,x',
        ], ['X-Internal-Secret' => self::SECRET])->assertNotFound();
    }

    public function test_webhook_rejects_an_unknown_event_type(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->postJson('/internal/worker/events', [
            'event' => 'something.else',
            'instance_id' => $instance->instance_id,
        ], ['X-Internal-Secret' => self::SECRET])->assertStatus(422);
    }

    public function test_message_received_event_stores_an_incoming_message(): void
    {
        Http::fake(); // no webhook_url configured, so nothing should be sent at all

        $instance = WhatsappSession::factory()->connected()->create();

        $response = $this->postJson('/internal/worker/events', [
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => '919999999999',
            'message' => 'Hi there',
            'whatsapp_message_id' => 'WA-INCOMING-1',
            'timestamp' => now()->toIso8601String(),
        ], ['X-Internal-Secret' => self::SECRET]);

        $response->assertNoContent();

        $message = Message::where('whatsapp_session_id', $instance->id)->firstOrFail();
        $this->assertSame('incoming', $message->direction);
        $this->assertSame('919999999999', $message->from_number);
        $this->assertSame('Hi there', $message->body);
        $this->assertSame('WA-INCOMING-1', $message->whatsapp_message_id);
        $this->assertSame('received', $message->status);

        Http::assertNothingSent();
    }

    public function test_message_received_event_dispatches_to_the_configured_webhook_with_a_valid_signature(): void
    {
        Http::fake(['https://example.test/webhook' => Http::response('', 200)]);

        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $this->postJson('/internal/worker/events', [
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => '919999999999',
            'message' => 'Hi there',
            'whatsapp_message_id' => 'WA-INCOMING-2',
            'timestamp' => now()->toIso8601String(),
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        Http::assertSent(function ($request) {
            $expectedSignature = 'sha256='.hash_hmac('sha256', $request->body(), 'a-fixed-webhook-secret');

            return $request->url() === 'https://example.test/webhook'
                && $request->header('X-Webhook-Signature')[0] === $expectedSignature
                && json_decode($request->body(), true)['message'] === 'Hi there';
        });
    }

    public function test_webhook_dispatch_failure_does_not_fail_the_original_request(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $response = $this->postJson('/internal/worker/events', [
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => '919999999999',
            'message' => 'Hi there',
            'whatsapp_message_id' => 'WA-INCOMING-3',
            'timestamp' => now()->toIso8601String(),
        ], ['X-Internal-Secret' => self::SECRET]);

        $response->assertNoContent();
        $this->assertSame(1, Message::where('whatsapp_session_id', $instance->id)->count());
    }
}
