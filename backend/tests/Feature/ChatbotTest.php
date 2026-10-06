<?php

namespace Tests\Feature;

use App\Jobs\SendChatbotReply;
use App\Models\ChatbotRule;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Chatbot step 1: managing keyword → answer entries (no auto-replies yet).
 */
class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return array_merge([
            'question' => 'What are your prices?',
            'keywords' => 'Price,  COST , price, rate',
            'answer' => 'Plans start at ₹499/month.',
        ], $overrides);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/chatbot')->assertRedirect(route('login'));
    }

    public function test_a_user_without_instances_is_asked_to_create_one(): void
    {
        $this->actingAs(User::factory()->create())->get('/chatbot')
            ->assertOk()
            ->assertSee('Create an instance first.');
    }

    public function test_entries_can_be_added_edited_and_deleted(): void
    {
        $instance = WhatsappSession::factory()->create();
        $base = "/chatbot/{$instance->instance_id}/rules";
        $list = route('chatbot.index', ['instance' => $instance->instance_id]);

        $this->actingAs($instance->user)->post($base, $this->form())->assertRedirect($list);
        $rule = ChatbotRule::sole();

        // Cleaned up: trimmed, lower-cased, duplicates removed.
        $this->assertSame(['price', 'cost', 'rate'], $rule->keywords);
        $this->assertSame($instance->id, $rule->whatsapp_session_id);

        $this->actingAs($instance->user)->get($list)->assertOk()
            ->assertSee('What are your prices?')
            ->assertSee('Plans start at ₹499/month.');
        $this->actingAs($instance->user)->get("{$base}/{$rule->id}/edit")->assertOk()->assertSee('price, cost, rate');

        $this->actingAs($instance->user)->put("{$base}/{$rule->id}", $this->form(['keywords' => 'fees', 'answer' => 'New answer']))
            ->assertRedirect($list);
        $this->assertSame(['fees'], $rule->fresh()->keywords);
        $this->assertSame('New answer', $rule->fresh()->answer);

        $this->actingAs($instance->user)->delete("{$base}/{$rule->id}")->assertRedirect($list);
        $this->assertDatabaseCount('chatbot_rules', 0);
    }

    public function test_the_page_shows_the_picked_instances_entries_only(): void
    {
        $first = WhatsappSession::factory()->create(['name' => 'Alpha']);
        $second = WhatsappSession::factory()->for($first->user)->create(['name' => 'Beta']);
        $first->chatbotRules()->create(['question' => 'Alpha question', 'keywords' => ['a'], 'answer' => 'A']);
        $second->chatbotRules()->create(['question' => 'Beta question', 'keywords' => ['b'], 'answer' => 'B']);

        $this->actingAs($first->user)->get('/chatbot?instance='.$second->instance_id)->assertOk()
            ->assertSee('Beta question')
            ->assertDontSee('Alpha question');
    }

    public function test_keywords_are_validated(): void
    {
        $instance = WhatsappSession::factory()->create();
        $url = "/chatbot/{$instance->instance_id}/rules";

        $this->actingAs($instance->user)->post($url, $this->form(['keywords' => ' , ,']))->assertSessionHasErrors('keywords');
        $this->actingAs($instance->user)->post($url, $this->form(['keywords' => implode(',', range(1, 21))]))->assertSessionHasErrors('keywords');
        $this->actingAs($instance->user)->post($url, $this->form(['keywords' => str_repeat('a', 51)]))->assertSessionHasErrors('keywords');
        $this->actingAs($instance->user)->post($url, $this->form(['answer' => '']))->assertSessionHasErrors('answer');

        $this->assertDatabaseCount('chatbot_rules', 0);
    }

    public function test_another_users_instance_and_entries_are_a_404(): void
    {
        $instance = WhatsappSession::factory()->create();
        $rule = $instance->chatbotRules()->create(['question' => 'Mine', 'keywords' => ['hi'], 'answer' => 'Hello']);
        $stranger = User::factory()->create();
        $base = "/chatbot/{$instance->instance_id}/rules";

        $this->actingAs($stranger)->post($base, $this->form())->assertNotFound();
        $this->actingAs($stranger)->get("{$base}/{$rule->id}/edit")->assertNotFound();
        $this->actingAs($stranger)->put("{$base}/{$rule->id}", $this->form())->assertNotFound();
        $this->actingAs($stranger)->delete("{$base}/{$rule->id}")->assertNotFound();

        // A foreign instance in the dropdown just isn't shown.
        $this->actingAs($stranger)->get('/chatbot?instance='.$instance->instance_id)->assertOk()->assertDontSee('Mine');

        $this->assertSame('Hello', $rule->fresh()->answer);
    }

    public function test_an_entry_cant_be_reached_through_another_instance(): void
    {
        $instance = WhatsappSession::factory()->create();
        $other = WhatsappSession::factory()->for($instance->user)->create();
        $rule = $instance->chatbotRules()->create(['question' => 'Q', 'keywords' => ['hi'], 'answer' => 'Hello']);

        $this->actingAs($instance->user)->get("/chatbot/{$other->instance_id}/rules/{$rule->id}/edit")->assertNotFound();
    }

    // --- Step 2: matching + Test bot box ------------------------------------------

    public function test_keywords_match_whole_words_ignoring_case(): void
    {
        $rule = new ChatbotRule(['keywords' => ['hi', 'price', 'opening time', '₹499']]);

        $this->assertTrue($rule->matches('Hi'));
        $this->assertTrue($rule->matches('hi, what is the PRICE?'));
        $this->assertTrue($rule->matches('price.'));
        $this->assertTrue($rule->matches('What is your opening   time?'));
        $this->assertTrue($rule->matches('Is it ₹499?'));

        $this->assertFalse($rule->matches('this is great'));
        $this->assertFalse($rule->matches('priceless'));
        $this->assertFalse($rule->matches('opening hours'));
        $this->assertFalse($rule->matches('My order is late'));
    }

    public function test_singular_and_plural_match_each_other(): void
    {
        $this->assertTrue((new ChatbotRule(['keywords' => ['sneakers']]))->matches('price of this sneaker?'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['shoe']]))->matches('Do you have SHOES?'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['price']]))->matches('prices please'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['box']]))->matches('3 boxes'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['watches']]))->matches('a watch'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['battery']]))->matches('batteries'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['opening time']]))->matches('opening times?'));

        // Left alone: short words, "ss" words, non-English.
        $this->assertFalse((new ChatbotRule(['keywords' => ['hi']]))->matches('his'));
        $this->assertFalse((new ChatbotRule(['keywords' => ['glass']]))->matches('glas'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['glass']]))->matches('glasses'));
        $this->assertTrue((new ChatbotRule(['keywords' => ['₹499']]))->matches('only ₹499'));

        // Both forms in one entry are counted once.
        $this->assertSame(['shoe'], (new ChatbotRule(['keywords' => ['shoe', 'shoes']]))->matchedKeywords('shoes?'));
    }

    public function test_the_entry_with_most_matching_keywords_wins(): void
    {
        $instance = WhatsappSession::factory()->create();
        $shoes = $instance->chatbotRules()->create([
            'question' => 'Shoes', 'keywords' => ['shoes', 'shoe', 'boot'],
            'answer' => 'Absolutely! We have running shoes, sneakers, casual shoes, and sports shoes available.',
        ]);
        $prices = $instance->chatbotRules()->create([
            'question' => 'Prices', 'keywords' => ['shoe', 'shoes', 'pairs', 'sneakers', 'price', 'rate', 'cost'],
            'answer' => 'Absolutely, you can check this product price from our website!',
        ]);
        $rules = $instance->chatbotRules()->get();

        // Only the second entry matches ("price" and "sneaker").
        $this->assertTrue($prices->is(ChatbotRule::bestMatch($rules, 'what is the price of this sneaker?')));
        // Both match "shoes", but the second also has "price" — it wins.
        $this->assertTrue($prices->is(ChatbotRule::bestMatch($rules, 'what is the price of these shoes?')));
        // A tie (both only "shoes"): the higher one in the list wins.
        $this->assertTrue($shoes->is(ChatbotRule::bestMatch($rules, 'do you have shoes?')));
        // Only the first has "boot(s)".
        $this->assertTrue($shoes->is(ChatbotRule::bestMatch($rules, 'any boots?')));
        $this->assertNull(ChatbotRule::bestMatch($rules, 'ok thanks'));
    }

    public function test_the_test_box_shows_the_reply_or_silence(): void
    {
        $instance = WhatsappSession::factory()->create();
        $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price'], 'answer' => 'Plans start at ₹499.']);
        $url = "/chatbot/{$instance->instance_id}/test";
        $list = route('chatbot.index', ['instance' => $instance->instance_id]);

        $this->actingAs($instance->user)->post($url, ['test_message' => 'What is the PRICE?'])->assertRedirect($list.'#test');
        $this->actingAs($instance->user)->get($list)->assertSee('Matched entry 1: Prices')->assertSee('Keywords found:');

        $this->actingAs($instance->user)->post($url, ['test_message' => 'My order is late']);
        $this->actingAs($instance->user)->get($list)->assertSee('the bot would stay silent');

        $this->actingAs($instance->user)->post($url, ['test_message' => ''])->assertSessionHasErrors('test_message');
    }

    public function test_another_users_instance_cant_be_tested(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->actingAs(User::factory()->create())->post("/chatbot/{$instance->instance_id}/test", ['test_message' => 'hi'])
            ->assertNotFound();
    }

    public function test_entries_are_limited_per_instance(): void
    {
        $instance = WhatsappSession::factory()->create();
        for ($i = 0; $i < ChatbotRule::MAX_PER_INSTANCE; $i++) {
            $instance->chatbotRules()->create(['question' => "Q{$i}", 'keywords' => ["k{$i}"], 'answer' => 'A']);
        }

        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/rules", $this->form())
            ->assertSessionHasErrors('question');
        $this->assertDatabaseCount('chatbot_rules', ChatbotRule::MAX_PER_INSTANCE);
    }

    // --- Step 3: switch + real replies ---------------------------------------------

    private const SECRET = 'test-shared-secret-value-1234567890';

    private function botInstance(bool $enabled = true): WhatsappSession
    {
        $instance = WhatsappSession::factory()->connected()->create(['chatbot_enabled' => $enabled]);
        $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price'], 'answer' => 'Plans start at ₹499.']);

        return $instance;
    }

    /**
     * The worker reporting a received message. Queued jobs run straight
     * away in tests (sync queue) unless Queue::fake() is used.
     */
    private function receive(WhatsappSession $instance, string $text, array $extra = []): Message
    {
        config(['worker.secret' => self::SECRET]);

        $this->postJson('/internal/worker/events', array_merge([
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => '919999999999',
            'from_is_lid' => false,
            'message' => $text,
            'whatsapp_message_id' => 'WA-IN-'.uniqid(),
            'timestamp' => now()->toIso8601String(),
        ], $extra), ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        return Message::where('direction', 'incoming')->latest('id')->first();
    }

    private function outgoingCount(): int
    {
        return Message::where('direction', 'outgoing')->count();
    }

    public function test_the_switch_turns_the_bot_on_and_off(): void
    {
        $instance = $this->botInstance(enabled: false);
        $url = "/chatbot/{$instance->instance_id}/toggle";

        $this->actingAs($instance->user)->post($url, ['enabled' => 1])->assertSessionHas('status');
        $this->assertTrue($instance->fresh()->chatbot_enabled);

        $this->actingAs($instance->user)->post($url, ['enabled' => 0]);
        $this->assertFalse($instance->fresh()->chatbot_enabled);

        $this->actingAs(User::factory()->create())->post($url, ['enabled' => 1])->assertNotFound();
        $this->assertFalse($instance->fresh()->chatbot_enabled);
    }

    public function test_the_bot_cant_be_switched_on_without_entries(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/toggle", ['enabled' => 1])
            ->assertSessionHasErrors('enabled');
        $this->assertFalse($instance->fresh()->chatbot_enabled);
    }

    public function test_a_matching_message_gets_the_answer(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-BOT-1'], 200)]);
        $instance = $this->botInstance();

        $this->receive($instance, 'What is the PRICE?');

        $reply = Message::where('direction', 'outgoing')->sole();
        $this->assertSame('919999999999', $reply->to_number);
        $this->assertSame('Plans start at ₹499.', $reply->body);
        $this->assertSame('sent', $reply->status);
        $this->assertSame($instance->chatbotRules()->first()->id, $reply->chatbot_rule_id);

        Http::assertSent(fn ($request) => $request['to'] === '919999999999' && $request['message'] === 'Plans start at ₹499.');

        // Shown with a "Bot" label on the Messages page.
        $this->actingAs($instance->user)->get('/messages')->assertSee('Sent automatically by the chatbot');
    }

    public function test_no_match_means_no_reply(): void
    {
        Http::fake();

        $this->receive($this->botInstance(), 'My order is late');

        $this->assertSame(0, $this->outgoingCount());
        Http::assertNothingSent();
    }

    public function test_nothing_is_queued_when_off_or_from_a_lid(): void
    {
        Queue::fake();

        $this->receive($this->botInstance(enabled: false), 'price');
        $on = $this->botInstance();
        $this->receive($on, 'price', ['from' => '248600000000055', 'from_is_lid' => true]);

        // An older worker that doesn't say whether it's a LID: not trusted.
        config(['worker.secret' => self::SECRET]);
        $this->postJson('/internal/worker/events', [
            'event' => 'message.received',
            'instance_id' => $on->instance_id,
            'from' => '919999999999',
            'message' => 'price',
            'whatsapp_message_id' => 'WA-OLD-WORKER',
            'timestamp' => now()->toIso8601String(),
        ], ['X-Internal-Secret' => self::SECRET])->assertNoContent();

        Queue::assertNotPushed(SendChatbotReply::class);

        $this->receive($on, 'price');
        Queue::assertPushed(SendChatbotReply::class, 1);
    }

    public function test_no_reply_when_disconnected_or_switched_off_meanwhile(): void
    {
        Http::fake();
        Queue::fake();
        $instance = $this->botInstance();
        $incoming = $this->receive($instance, 'price');
        $job = new SendChatbotReply($incoming->id);

        $instance->update(['status' => 'disconnected']);
        $job->handle(app(MessageSender::class), app(PlanLimiter::class));

        $instance->update(['status' => 'connected', 'chatbot_enabled' => false]);
        $job->handle(app(MessageSender::class), app(PlanLimiter::class));

        Http::assertNothingSent();
        $this->assertSame(0, $this->outgoingCount());
    }

    public function test_the_same_answer_isnt_repeated_within_two_minutes(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'x'], 200)]);
        $instance = $this->botInstance();

        $this->receive($instance, 'price');
        $this->receive($instance, 'price?');
        $this->assertSame(1, $this->outgoingCount());

        $this->travel(3)->minutes();
        $this->receive($instance, 'price again');
        $this->assertSame(2, $this->outgoingCount());
    }

    public function test_one_person_gets_at_most_ten_bot_replies_an_hour(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'x'], 200)]);
        $instance = $this->botInstance();

        // 12 messages, 3 minutes apart — all within one hour.
        for ($i = 0; $i < 12; $i++) {
            $this->receive($instance, 'price');
            $this->travel(3)->minutes();
        }

        $this->assertSame(ChatbotRule::MAX_REPLIES_PER_CONTACT_PER_HOUR, $this->outgoingCount());
    }

    public function test_no_reply_once_the_monthly_limit_is_reached(): void
    {
        Http::fake();
        $instance = $this->botInstance();
        // Free plan's limit is 50 messages/month (config/plans.php).
        Message::factory()->for($instance, 'whatsappSession')->count(50)->create();

        $this->receive($instance, 'price');

        $this->assertSame(50, $this->outgoingCount());
        Http::assertNothingSent();
    }
}
