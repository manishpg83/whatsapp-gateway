<?php

namespace Tests\Feature;

use App\Models\InboxConversation;
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

    public function test_each_conversation_remembers_its_latest_message(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Hi');
        $reply = $this->outgoing($instance, '919811111111', 'Hello!');
        $other = $this->incoming($instance, '919822222222', 'Timings?');
        Message::factory()->for($instance, 'whatsappSession')->create(['to_number' => null]); // no number: no conversation

        $this->assertSame(
            ['919811111111' => $reply->id, '919822222222' => $other->id],
            InboxConversation::where('whatsapp_session_id', $instance->id)->orderBy('phone')->pluck('last_message_id', 'phone')->all()
        );

        // An older chat moves back to the top when a new message arrives.
        $this->incoming($instance, '919811111111', 'One more thing');

        $this->actingAs($instance->user)->get('/inbox')->assertSeeInOrder(['+919811111111', 'One more thing', '+919822222222']);
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

    public function test_conversations_can_be_searched_by_name(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Alpha message');
        $this->incoming($instance, '919822222222', 'Beta message');
        InboxConversation::where('phone', '919822222222')->update(['name' => 'Ramesh Kumar']);

        $this->actingAs($instance->user)->get('/inbox?search=ramesh')
            ->assertSee('Beta message')
            ->assertDontSee('Alpha message');

        // A number typed with + and spaces still matches.
        $this->actingAs($instance->user)->get('/inbox?search='.urlencode('+91 98111'))
            ->assertSee('Alpha message')
            ->assertDontSee('Beta message');

        // % is matched literally, not as "anything".
        $this->actingAs($instance->user)->get('/inbox?search=%25')
            ->assertSee('No matching conversations.');
    }

    public function test_earlier_messages_can_be_loaded(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'The very first message');
        foreach (range(1, 100) as $i) {
            $this->incoming($instance, '919811111111', "Later message {$i}");
        }

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')
            ->assertSee('Load earlier messages')
            ->assertSee('older=2', false)
            ->assertDontSee('The very first message');

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111&older=2')
            ->assertSee('The very first message')
            ->assertDontSee('Load earlier messages');

        // The live updates use the same page count.
        $json = $this->actingAs($instance->user)
            ->getJson("/inbox/{$instance->instance_id}/updates?chat=919811111111&older=2")
            ->json();
        $this->assertStringContainsString('The very first message', $json['thread']);
    }

    public function test_more_chats_can_be_loaded(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919800000000', 'The oldest chat');
        foreach (range(1, 100) as $i) {
            $this->incoming($instance, '9198'.str_pad((string) $i, 8, '0', STR_PAD_LEFT), "Chat {$i}");
        }

        $this->actingAs($instance->user)->get('/inbox')
            ->assertSee('Load more chats')
            ->assertDontSee('The oldest chat');

        $this->actingAs($instance->user)->get('/inbox?chats=2')
            ->assertSee('The oldest chat')
            ->assertDontSee('Load more chats');

        $json = $this->actingAs($instance->user)->getJson("/inbox/{$instance->instance_id}/updates?chats=2")->json();
        $this->assertStringContainsString('The oldest chat', $json['list']);
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

    // --- Unread -----------------------------------------------------------------

    public function test_the_unread_filter_shows_only_unread_chats(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Already read');
        $this->incoming($instance, '919822222222', 'Still unread');
        InboxConversation::where('phone', '919822222222')->update(['unread_count' => 2]);

        $this->actingAs($instance->user)->get('/inbox')
            ->assertSee('Already read')->assertSee('Still unread');

        $this->actingAs($instance->user)->get('/inbox?filter=unread')
            ->assertSee('Still unread')->assertDontSee('Already read')
            // Opening a chat keeps the filter in its link.
            ->assertSee('filter=unread&amp;chat=919822222222', false);

        // The open chat stays listed, though opening it made it read.
        $this->actingAs($instance->user)->get('/inbox?filter=unread&chat=919822222222')->assertSee('Still unread');
        $this->assertSame(0, InboxConversation::where('phone', '919822222222')->value('unread_count'));

        $this->actingAs($instance->user)->get('/inbox?filter=unread')->assertSee('No unread chats.');
    }

    public function test_a_chat_can_be_marked_as_unread(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Hello');

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSee('Mark unread');

        // Back to the list (staying in the chat would mark it read again).
        $this->actingAs($instance->user)
            ->post("/inbox/{$instance->instance_id}/unread", ['chat' => '919811111111'])
            ->assertRedirect(route('inbox.index', ['instance' => $instance->instance_id]))
            ->assertSessionHas('status', 'Marked as unread.');

        $this->assertSame(1, InboxConversation::sole()->unread_count);
        $this->actingAs($instance->user)->get('/inbox?filter=unread')->assertSee('Hello');

        // Someone else's chat: not found.
        $other = WhatsappSession::factory()->create();
        $this->incoming($other, '919822222222', 'Not yours');
        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/unread", ['chat' => '919822222222'])->assertNotFound();
    }

    // --- Rename -----------------------------------------------------------------

    public function test_a_contact_can_be_given_your_own_name(): void
    {
        $instance = WhatsappSession::factory()->create();
        $this->incoming($instance, '919811111111', 'Hello');
        InboxConversation::where('phone', '919811111111')->update(['name' => 'RK']);

        $this->actingAs($instance->user)
            ->post("/inbox/{$instance->instance_id}/name", ['chat' => '919811111111', 'name' => '  Ramesh – Pune shop '])
            ->assertRedirect(route('inbox.index', ['instance' => $instance->instance_id, 'chat' => '919811111111']))
            ->assertSessionHas('status', 'Name saved.');

        $this->assertSame('Ramesh – Pune shop', InboxConversation::sole()->custom_name);
        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSee('Ramesh – Pune shop')->assertSee('Use WhatsApp name');
        $this->actingAs($instance->user)->get('/inbox?search=pune')->assertSee('Hello');

        // A new WhatsApp name is remembered, but doesn't replace yours.
        InboxConversation::messageReceived($instance, '919811111111', 'Ramesh K');
        $this->actingAs($instance->user)->get('/inbox')->assertSee('Ramesh – Pune shop')->assertDontSee('Ramesh K<');

        // Back to the WhatsApp name.
        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/name", ['chat' => '919811111111', 'name' => 'x', 'reset' => '1']);
        $this->assertNull(InboxConversation::sole()->custom_name);
        $this->actingAs($instance->user)->get('/inbox')->assertSee('Ramesh K');
    }

    public function test_only_your_own_conversations_can_be_renamed(): void
    {
        $instance = WhatsappSession::factory()->create();
        $other = WhatsappSession::factory()->create();
        $this->incoming($other, '919822222222', 'Not yours');

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/name", ['chat' => '919822222222', 'name' => 'Mine'])->assertNotFound();
        $this->actingAs($instance->user)->post("/inbox/{$other->instance_id}/name", ['chat' => '919822222222', 'name' => 'Mine'])->assertNotFound();
        $this->assertNull(InboxConversation::sole()->custom_name);
    }

    // --- Retry ------------------------------------------------------------------

    public function test_a_failed_message_can_be_retried(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-RETRY'], 200)]);
        $instance = WhatsappSession::factory()->connected()->create(['chatbot_pause_minutes' => 30]);
        $this->incoming($instance, '919811111111', 'Hello?');
        $failed = $this->outgoing($instance, '919811111111', 'Sorry for the wait', ['status' => 'failed', 'error' => 'Timed out']);

        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertSee('Retry');

        $this->actingAs($instance->user)
            ->post("/inbox/{$instance->instance_id}/messages/{$failed->id}/retry")
            ->assertRedirect(route('inbox.index', ['instance' => $instance->instance_id, 'chat' => '919811111111']));

        // A new message went out; the failed one stays as history.
        $sent = Message::where('status', 'sent')->sole();
        $this->assertSame('Sorry for the wait', $sent->body);
        $this->assertSame('919811111111', $sent->to_number);
        $this->assertSame('WA-RETRY', $sent->whatsapp_message_id);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame(1, $instance->chatbotPauses()->count());

        // It went through, so no more Retry button.
        $this->actingAs($instance->user)->get('/inbox?chat=919811111111')->assertDontSee('Retry');
    }

    public function test_only_your_own_failed_messages_can_be_retried(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->connected()->create();
        $sent = $this->outgoing($instance, '919811111111', 'Went fine');
        $incoming = $this->incoming($instance, '919811111111', 'From the customer');
        $someoneElses = $this->outgoing(WhatsappSession::factory()->connected()->create(), '919822222222', 'Not mine', ['status' => 'failed']);

        foreach ([$sent, $incoming, $someoneElses] as $message) {
            $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/messages/{$message->id}/retry")->assertNotFound();
        }

        Http::assertNothingSent();
    }

    public function test_retry_needs_a_connected_instance(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->create(['status' => 'disconnected']);
        $failed = $this->outgoing($instance, '919811111111', 'Hi', ['status' => 'failed']);

        $this->actingAs($instance->user)->post("/inbox/{$instance->instance_id}/messages/{$failed->id}/retry")
            ->assertSessionHas('error', 'This instance is not connected. Reconnect it to retry.');

        Http::assertNothingSent();
    }
}
