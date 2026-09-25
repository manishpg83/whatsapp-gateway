<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\InstanceDisconnected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

    public function test_an_auto_retrying_disconnect_shows_as_connecting(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'disconnected',
            'auto_retry' => true,
            'last_disconnect_reason' => 'Connection lost. Reconnecting automatically (attempt 1 of 6)…',
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        $instance->refresh();
        $this->assertSame('connecting', $instance->status);
        $this->assertNotNull($instance->phone_number);
    }

    public function test_a_final_disconnect_shows_as_disconnected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'disconnected',
            'auto_retry' => false,
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        $this->assertSame('disconnected', $instance->fresh()->status);
    }

    public function test_connection_changes_are_recorded_in_the_history(): void
    {
        $instance = WhatsappSession::factory()->create(['status' => 'qr_pending']);
        $send = fn (array $payload) => $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            ...$payload,
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        $send(['status' => 'connected', 'phone_number' => '919999999999']);
        $send(['status' => 'disconnected', 'auto_retry' => true]);
        $send(['status' => 'disconnected', 'auto_retry' => true]); // 2nd attempt: not logged again
        $send(['status' => 'disconnected', 'auto_retry' => false, 'last_disconnect_reason' => 'Gave up']);

        $this->assertSame(
            ['connected', 'connection_lost', 'disconnected'],
            $instance->events()->orderBy('id')->pluck('type')->all()
        );
        $this->assertSame('Gave up', $instance->events()->where('type', 'disconnected')->value('detail'));
    }

    public function test_owner_is_emailed_when_a_linked_instance_goes_offline_for_good(): void
    {
        Notification::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'logged_out',
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        Notification::assertSentTo($instance->user, InstanceDisconnected::class, function (InstanceDisconnected $notification) use ($instance) {
            $html = (string) $notification->toMail($instance->user)->render();

            return str_contains($html, e($instance->name)) && str_contains($html, 'scan a new QR code');
        });
    }

    public function test_owner_is_not_emailed_while_the_worker_is_auto_reconnecting(): void
    {
        Notification::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'disconnected',
            'auto_retry' => true,
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_owner_is_not_emailed_when_a_never_linked_qr_expires(): void
    {
        Notification::fake();
        $instance = WhatsappSession::factory()->create(['status' => 'qr_pending', 'phone_number' => null]);

        $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'disconnected',
            'auto_retry' => false,
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_owner_is_not_emailed_twice_for_an_already_offline_instance(): void
    {
        Notification::fake();
        $instance = WhatsappSession::factory()->create(['status' => 'disconnected', 'phone_number' => '919999999999']);

        $this->postJson('/internal/worker/events', [
            'event' => 'connection.updated',
            'instance_id' => $instance->instance_id,
            'status' => 'logged_out',
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        Notification::assertNothingSent();
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
        $this->assertNull($instance->phone_number);
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
        Queue::fake(); // no webhook_url configured, so nothing should be queued at all

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

        Queue::assertNothingPushed();
    }

    public function test_message_received_event_queues_a_webhook_delivery_when_one_is_configured(): void
    {
        Queue::fake();

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

        // The actual signed HTTP delivery is DeliverWebhook's own job —
        // tested directly in tests/Feature/DeliverWebhookTest.php. Here we
        // only need to confirm the right job was queued with the right data.
        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job) => $job->whatsappSessionId === $instance->id
                && $job->payload['message'] === 'Hi there'
                && $job->payload['from'] === '919999999999'
        );
    }

    public function test_queuing_a_webhook_delivery_never_fails_the_original_request(): void
    {
        // Queuing onto the database driver can't itself fail the way a
        // live HTTP call could — that's the whole point of moving
        // delivery into a job. This just confirms the request completes
        // normally and the message is stored regardless.
        Queue::fake();

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
