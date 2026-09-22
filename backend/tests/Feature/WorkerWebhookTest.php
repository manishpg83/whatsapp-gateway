<?php

namespace Tests\Feature;

use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
