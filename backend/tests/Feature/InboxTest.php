<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function incoming(WhatsappSession $instance, string $from, string $body, array $extra = []): Message
    {
        return Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'incoming', 'from_number' => $from, 'to_number' => null, 'body' => $body, 'status' => 'received', ...$extra,
        ]);
    }

    private function outgoing(WhatsappSession $instance, string $to, string $body, array $extra = []): Message
    {
        return Message::factory()->for($instance, 'whatsappSession')->create(['to_number' => $to, 'body' => $body, ...$extra]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/inbox')->assertRedirect(route('login'));
    }

    public function test_without_an_instance_it_asks_for_one(): void
    {
        $this->actingAs(User::factory()->create())->get('/inbox')->assertOk()->assertSee('Create an instance first.');
    }

    public function test_messages_are_grouped_into_conversations_newest_first(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Hi, do you deliver?');
        $this->outgoing($instance, '919811111111', 'Yes, everywhere in Pune.', ['bot_reply' => 'answer']);
        $this->incoming($instance, '919822222222', 'What are your timings?');

        $this->actingAs($instance->user)->get('/inbox')->assertOk()
            ->assertSee('Choose a conversation')
            // Newest conversation first; each shows its latest message.
            ->assertSeeInOrder(['+919822222222', 'What are your timings?', '+919811111111', 'Bot:', 'Yes, everywhere in Pune.']);
    }

    public function test_opening_a_conversation_shows_only_its_messages_oldest_first(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'First question');
        $this->outgoing($instance, '919811111111', 'Our answer', ['api_token_id' => null]);
        $this->incoming($instance, '919822222222', 'Someone else entirely');
        $this->outgoing($instance, '919811111111', 'Sorry, it failed', ['status' => 'failed', 'error' => 'Not on WhatsApp']);

        $response = $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertOk()
            ->assertSee('3 messages')
            ->assertSeeInOrder(['First question', 'Our answer', 'Sorry, it failed', 'Not sent: Not on WhatsApp']);

        // The other customer is only in the list on the left, not in this chat.
        $this->assertSame(1, substr_count($response->getContent(), 'Someone else entirely'));
    }

    public function test_conversations_can_be_searched_by_number(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Alpha message');
        $this->incoming($instance, '919822222222', 'Beta message');

        $this->actingAs($instance->user)->get('/inbox?search=2222')
            ->assertSee('Beta message')
            ->assertDontSee('Alpha message');
    }

    public function test_only_the_picked_instance_is_shown_and_it_is_remembered(): void
    {
        $first = WhatsappSession::factory()->create(['name' => 'Alpha']);
        $second = WhatsappSession::factory()->for($first->user)->create(['name' => 'Beta']);
        $this->incoming($first, '919811111111', 'To the first number');
        $this->incoming($second, '919822222222', 'To the second number');

        $this->actingAs($first->user)->get('/inbox?instance='.$second->instance_id)
            ->assertSee('choose which one to view')
            ->assertSee('To the second number')
            ->assertDontSee('To the first number');

        // The sidebar link opens the same instance again.
        $this->actingAs($first->user)->get('/inbox')->assertSee('To the second number');
    }

    // --- Replying ---------------------------------------------------------------

    public function test_a_reply_is_sent_to_the_open_chat_and_pauses_the_bot(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-REPLY'], 200)]);
        $instance = WhatsappSession::factory()->connected()->create(['chatbot_pause_minutes' => 30]);
        $this->incoming($instance, '919811111111', 'Is the blue one in stock?');

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSee('Type a reply');

        $this->actingAs($instance->user)
            ->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => "Yes!\nWe have 3 left."])
            ->assertRedirect(route('inbox.index', ['instance' => $instance->instance_id, 'chat' => '919811111111']));

        $reply = Message::where('direction', 'outgoing')->sole();
        $this->assertSame('919811111111', $reply->to_number);
        $this->assertSame("Yes!\nWe have 3 left.", $reply->body);
        $this->assertSame('sent', $reply->status);
        $this->assertSame('WA-REPLY', $reply->whatsapp_message_id);

        $pause = $instance->chatbotPauses()->sole();
        $this->assertSame('919811111111', $pause->phone);
        $this->assertTrue($pause->paused_until->isFuture());

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSeeInOrder(['Is the blue one in stock?', 'We have 3 left.']);
    }

    public function test_no_bot_pause_when_the_setting_is_off(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-REPLY'], 200)]);
        $instance = WhatsappSession::factory()->connected()->create(['chatbot_pause_minutes' => 0]);
        $this->incoming($instance, '919811111111', 'Hello');

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => 'Hi!']);

        $this->assertDatabaseCount('chatbot_pauses', 0);
    }

    public function test_a_reply_needs_a_connected_instance_and_room_on_the_plan(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->create(['status' => 'disconnected']);
        $this->incoming($instance, '919811111111', 'Hello');

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')
            ->assertSee("isn't connected, so you can't reply right now", false)
            ->assertDontSee('Type a reply');

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => 'Hi'])
            ->assertSessionHas('error', 'This instance is not connected. Reconnect it to reply.');

        // Connected, but the Free plan's monthly messages are used up.
        $instance->update(['status' => 'connected']);
        Message::factory()->count(50)->for($instance, 'whatsappSession')->create();

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => 'Hi'])
            ->assertSessionHas('error', "You've reached your plan's monthly message limit. Upgrade to send more.");

        Http::assertNothingSent();
    }

    public function test_replies_only_go_into_your_own_existing_conversations(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->connected()->create();
        $this->incoming($instance, '919811111111', 'Hello');

        // Not a conversation of this instance: no new chats from here.
        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919800000000', 'message' => 'Hi'])->assertNotFound();
        // Empty message.
        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => ''])
            ->assertSessionHasErrors(['message' => 'Type a message first.']);
        // Another user's instance.
        $this->actingAs(User::factory()->create())->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => 'Hi'])->assertNotFound();

        Http::assertNothingSent();
        $this->assertSame(0, Message::where('direction', 'outgoing')->count());
    }

    // --- Unread counts and live updates ------------------------------------------

    private function receiveFromWorker(WhatsappSession $instance, string $from, string $text): void
    {
        config(['worker.secret' => 'test-shared-secret-value-1234567890']);

        $this->postJson('/internal/worker/events', [
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => $from,
            'from_is_lid' => false,
            'message' => $text,
            'whatsapp_message_id' => 'WA-IN-'.uniqid(),
            'timestamp' => now()->toIso8601String(),
        ], ['X-Internal-Secret' => 'test-shared-secret-value-1234567890'])->assertNoContent();
    }

    public function test_incoming_messages_are_unread_until_the_chat_is_opened(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->receiveFromWorker($instance, '919811111111', 'First');
        $this->receiveFromWorker($instance, '919811111111', 'Second');
        $this->receiveFromWorker($instance, '919822222222', 'Other');

        $this->actingAs($instance->user)->get('/dashboard')->assertSee('data-ib-nav-badge >3</span>', false);
        $this->actingAs($instance->user)->get('/inbox')->assertSee('title="2 unread"', false)->assertSee('title="1 unread"', false);

        // Opening a chat reads it.
        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertDontSee('title="2 unread"', false);
        $this->assertSame(1, \App\Models\InboxConversation::unreadFor($instance->user));
    }

    public function test_replying_marks_the_chat_read(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-REPLY'], 200)]);
        $instance = WhatsappSession::factory()->connected()->create();
        $this->receiveFromWorker($instance, '919811111111', 'Hello?');

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => 'Hi!']);

        $this->assertSame(0, \App\Models\InboxConversation::unreadFor($instance->user));
    }

    public function test_updates_send_only_what_changed(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Old message');
        $url = "/inbox/{$instance->instance_id}/updates?chat=919811111111";

        $first = $this->actingAs($instance->user)->getJson($url)->assertOk()->json();
        $this->assertStringContainsString('Old message', $first['list']);
        $this->assertStringContainsString('Old message', $first['thread']);
        $this->assertSame('1 message', $first['thread_total']);

        // Same versions: nothing to redraw.
        $same = $this->actingAs($instance->user)
            ->getJson("{$url}&list={$first['list_version']}&thread={$first['thread_version']}")->json();
        $this->assertNull($same['list']);
        $this->assertNull($same['thread']);

        // A new message arrives while the chat is open and visible.
        $this->receiveFromWorker($instance, '919811111111', 'Brand new message');
        $next = $this->actingAs($instance->user)
            ->getJson("{$url}&list={$first['list_version']}&thread={$first['thread_version']}&seen=1")->json();
        $this->assertStringContainsString('Brand new message', $next['thread']);
        $this->assertStringContainsString('Brand new message', $next['list']);
        $this->assertSame(0, $next['unread_total']); // seen, so read

        // Not visible: stays unread.
        $this->receiveFromWorker($instance, '919811111111', 'While away');
        $this->assertSame(1, $this->actingAs($instance->user)->getJson($url)->json('unread_total'));
    }

    public function test_updates_are_only_for_your_own_instance(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->getJson("/inbox/{$instance->instance_id}/updates")->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson("/inbox/{$instance->instance_id}/updates")->assertNotFound();
    }

    // --- Messages typed on the phone, names, bot switch, files -------------------

    private function sentFromPhone(WhatsappSession $instance, string $to, array $extra = []): void
    {
        config(['worker.secret' => 'test-shared-secret-value-1234567890']);

        $this->postJson('/internal/worker/events', [
            'event' => 'message.sent_from_phone',
            'instance_id' => $instance->instance_id,
            'to' => $to,
            'to_is_lid' => false,
            'whatsapp_message_id' => 'WA-PHONE-'.uniqid(),
            'type' => 'text',
            'message' => 'Typed on my phone',
            'timestamp' => now()->toIso8601String(),
            'media' => null,
            ...$extra,
        ], ['X-Internal-Secret' => 'test-shared-secret-value-1234567890'])->assertNoContent();
    }

    public function test_messages_typed_on_the_phone_show_in_the_inbox_only(): void
    {
        $instance = WhatsappSession::factory()->create(['chatbot_pause_minutes' => 30]);
        $this->receiveFromWorker($instance, '919811111111', 'Is it in stock?');
        $this->sentFromPhone($instance, '919811111111', ['message' => 'Yes, 3 left!']);

        $phone = Message::where('direction', Message::DIRECTION_PHONE)->sole();
        $this->assertSame('919811111111', $phone->to_number);
        $this->assertSame('Yes, 3 left!', $phone->body);

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')
            ->assertSeeInOrder(['Is it in stock?', 'Yes, 3 left!', 'Phone']);

        // Answering on the phone means the owner saw the chat; the bot pauses.
        $this->assertSame(0, \App\Models\InboxConversation::unreadFor($instance->user));
        $this->assertTrue($instance->chatbotPauses()->where('phone', '919811111111')->exists());

        // Not a gateway message: not in the Messages log, not on the plan.
        $this->actingAs($instance->user)->get('/messages')->assertDontSee('Yes, 3 left!');
        $this->assertSame(0, app(\App\Services\PlanLimiter::class)->messagesSentThisMonth($instance->user));

        // It gets ticks, without calling the owner's webhook.
        Http::fake();
        $this->postJson('/internal/worker/events', [
            'event' => 'message.status', 'instance_id' => $instance->instance_id,
            'whatsapp_message_id' => $phone->whatsapp_message_id, 'status' => 'read',
        ], ['X-Internal-Secret' => 'test-shared-secret-value-1234567890'])->assertNoContent();
        $this->assertSame('read', $phone->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_an_older_worker_without_the_message_only_pauses_the_bot(): void
    {
        $instance = WhatsappSession::factory()->create(['chatbot_pause_minutes' => 30]);

        config(['worker.secret' => 'test-shared-secret-value-1234567890']);
        $this->postJson('/internal/worker/events', [
            'event' => 'message.sent_from_phone', 'instance_id' => $instance->instance_id,
            'to' => '919811111111', 'to_is_lid' => false, 'whatsapp_message_id' => 'WA-OLD',
        ], ['X-Internal-Secret' => 'test-shared-secret-value-1234567890'])->assertNoContent();

        $this->assertSame(0, Message::count());
        $this->assertTrue($instance->chatbotPauses()->exists());
    }

    public function test_the_customers_whatsapp_name_is_shown(): void
    {
        $instance = WhatsappSession::factory()->create();
        config(['worker.secret' => 'test-shared-secret-value-1234567890']);
        $receive = fn (?string $name) => $this->postJson('/internal/worker/events', [
            'event' => 'message.received', 'instance_id' => $instance->instance_id, 'from' => '919811111111',
            'from_is_lid' => false, 'name' => $name, 'message' => 'Hi', 'whatsapp_message_id' => 'WA-'.uniqid(),
            'timestamp' => now()->toIso8601String(),
        ], ['X-Internal-Secret' => 'test-shared-secret-value-1234567890'])->assertNoContent();

        $receive('Riya Shah');
        $receive(null); // a message without a name keeps the one we have

        $this->actingAs($instance->user)->get('/inbox')->assertSee('Riya Shah');
        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSeeInOrder(['Riya Shah', '+919811111111']);
    }

    public function test_the_bot_can_be_turned_off_and_on_for_one_chat(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-REPLY'], 200)]);
        $instance = WhatsappSession::factory()->connected()->create(['chatbot_enabled' => true, 'chatbot_pause_minutes' => 30]);
        $this->incoming($instance, '919811111111', 'Hello');
        $url = "/inbox/{$instance->instance_id}/bot";

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSee('Bot on');

        $this->actingAs($instance->user)->post($url, ['chat' => '919811111111', 'bot' => 'off'])
            ->assertRedirect(route('inbox.index', ['instance' => $instance->instance_id, 'chat' => '919811111111']));
        $pause = $instance->chatbotPauses()->sole();
        $this->assertTrue($pause->isTurnedOff());

        // A reply's short pause doesn't turn the bot back on.
        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", ['chat' => '919811111111', 'message' => 'Hi']);
        $this->assertTrue($pause->fresh()->isTurnedOff());

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSee('Bot off');
        $this->actingAs($instance->user)->get('/chatbot')->assertSee('turned off in the Inbox');

        $this->actingAs($instance->user)->post($url, ['chat' => '919811111111', 'bot' => 'on']);
        $this->assertDatabaseCount('chatbot_pauses', 0);

        // Only existing chats of your own instances.
        $this->actingAs($instance->user)->post($url, ['chat' => '919800000000', 'bot' => 'off'])->assertNotFound();
        $this->actingAs(User::factory()->create())->post($url, ['chat' => '919811111111', 'bot' => 'off'])->assertNotFound();
    }

    public function test_a_file_can_be_sent_with_an_optional_caption(): void
    {
        Storage::fake('whatsapp_media');
        Http::fake(['*' => Http::response(['message_id' => 'WA-FILE'], 200)]);
        $instance = WhatsappSession::factory()->connected()->create();
        $this->incoming($instance, '919811111111', 'Send me the price list');
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", [
            'chat' => '919811111111',
            'file' => UploadedFile::fake()->createWithContent('Price list.pdf', $pdf),
        ])->assertSessionHasNoErrors();

        $sent = Message::where('direction', 'outgoing')->sole();
        $this->assertSame('document', $sent->type);
        $this->assertSame('', $sent->body);
        $this->assertSame('Price list.pdf', $sent->media_file_name);
        $this->assertSame('sent', $sent->status);

        // A photo that isn't really one is refused.
        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/reply", [
            'chat' => '919811111111',
            'message' => 'Our shop',
            'file' => UploadedFile::fake()->createWithContent('shop.jpg', str_repeat("\0", 20)),
        ]);
        $this->assertSame(1, Message::where('direction', 'outgoing')->count());
    }

    public function test_another_users_messages_are_never_shown(): void
    {
        $owner = WhatsappSession::factory()->create();
        $this->incoming($owner, '919811111111', 'Private message');
        $stranger = WhatsappSession::factory()->create();

        // Their own instance is shown instead of the foreign one.
        $this->actingAs($stranger->user)->get('/inbox?instance='.$owner->instance_id.'&chat=919811111111')
            ->assertOk()
            ->assertDontSee('Private message')
            ->assertSee('No messages with this number yet.');
    }
}
