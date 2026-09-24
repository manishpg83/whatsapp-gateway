<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\ApiToken;
use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageReceiptTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappSession $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['worker.secret' => 'test-internal-secret-123456']);
        $this->instance = WhatsappSession::factory()->connected()->create();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function sentMessage(string $status = 'sent'): Message
    {
        return Message::factory()->for($this->instance, 'whatsappSession')->create([
            'direction' => 'outgoing',
            'status' => $status,
            'whatsapp_message_id' => 'WA-1',
            'to_number' => '919999999999',
        ]);
    }

    private function receipt(string $status, string $messageId = 'WA-1')
    {
        return $this->withHeaders(['X-Internal-Secret' => 'test-internal-secret-123456'])
            ->postJson(route('internal.worker.events'), [
                'event' => 'message.status',
                'instance_id' => $this->instance->instance_id,
                'whatsapp_message_id' => $messageId,
                'status' => $status,
            ]);
    }

    public function test_sent_becomes_delivered_then_read(): void
    {
        $message = $this->sentMessage();

        $this->receipt('delivered')->assertNoContent();
        $message->refresh();
        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertNull($message->read_at);

        $this->receipt('read')->assertNoContent();
        $message->refresh();
        $this->assertSame('read', $message->status);
        $this->assertNotNull($message->read_at);
    }

    public function test_read_straight_away_also_sets_delivered_at(): void
    {
        $message = $this->sentMessage();

        $this->receipt('read');

        $message->refresh();
        $this->assertSame('read', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_a_late_delivered_never_downgrades_read(): void
    {
        $message = $this->sentMessage('read');

        $this->receipt('delivered')->assertNoContent();

        $this->assertSame('read', $message->fresh()->status);
    }

    public function test_a_failed_message_stays_failed(): void
    {
        $message = $this->sentMessage('failed');

        $this->receipt('delivered');

        $this->assertSame('failed', $message->fresh()->status);
    }

    public function test_receipts_for_unknown_messages_are_ignored(): void
    {
        // e.g. a message the owner typed on their own phone.
        $this->receipt('read', 'NOT-OURS')->assertNoContent();

        $this->assertSame(0, Message::count());
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $this->sentMessage();

        $this->receipt('exploded')->assertStatus(422);
    }

    public function test_the_customer_gets_a_message_status_webhook(): void
    {
        Queue::fake();
        $this->instance->update(['webhook_url' => 'https://example.test/hook', 'webhook_secret' => 'secret']);
        $this->sentMessage();

        $this->receipt('read');

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->payload['event'] === 'message.status'
            && $job->payload['message_id'] === 'WA-1'
            && $job->payload['status'] === 'read'
            && $job->payload['to'] === '919999999999');
    }

    public function test_status_api_returns_the_receipt_times(): void
    {
        ['plainText' => $token] = ApiToken::generateFor($this->instance, 'x');
        $this->sentMessage();
        $this->receipt('read');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/messages/WA-1')
            ->assertOk()
            ->assertJsonPath('message.status', 'read')
            ->assertJsonStructure(['message' => ['delivered_at', 'read_at']]);
    }

    public function test_delivered_and_read_still_count_as_sent(): void
    {
        $user = $this->instance->user;
        $this->sentMessage('delivered');
        Message::factory()->for($this->instance, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'read']);

        $this->actingAs($user)->get(route('instances.show', $this->instance))
            ->assertOk()
            ->assertSee('2 sent')
            ->assertSee('Delivered')
            ->assertSee('Read');
    }

    public function test_messages_page_can_filter_by_read(): void
    {
        $user = $this->instance->user;
        Message::factory()->for($this->instance, 'whatsappSession')->create(['status' => 'read', 'body' => 'Was read']);
        Message::factory()->for($this->instance, 'whatsappSession')->create(['status' => 'sent', 'body' => 'Only sent']);

        $this->actingAs($user)->get(route('messages.index', ['status' => 'read']))
            ->assertOk()
            ->assertSee('Was read')
            ->assertDontSee('Only sent');
    }
}
