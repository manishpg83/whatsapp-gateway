<?php

namespace Tests\Feature;

use App\Jobs\SendChatbotReply;
use App\Models\ChatbotRule;
use App\Models\Message;
use App\Models\Plan;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use App\Support\ChatbotHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        $instance->user->subscription->update(['plan' => 'business']); // 1000 entries, so the plan isn't what stops it
        for ($i = 0; $i < ChatbotRule::MAX_PER_INSTANCE; $i++) {
            $instance->chatbotRules()->create(['question' => "Q{$i}", 'keywords' => ["k{$i}"], 'answer' => 'A']);
        }

        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/rules", $this->form())
            ->assertSessionHasErrors(['question' => 'This instance already has '.ChatbotRule::MAX_PER_INSTANCE.' entries. Delete one first.']);
        $this->assertDatabaseCount('chatbot_rules', ChatbotRule::MAX_PER_INSTANCE);
    }

    // --- Plan limits ------------------------------------------------------------------

    public function test_entries_are_limited_by_plan_across_all_instances(): void
    {
        // Free plan: 5 chatbot entries.
        $first = WhatsappSession::factory()->create();
        $second = WhatsappSession::factory()->for($first->user)->create();
        for ($i = 0; $i < 3; $i++) {
            $first->chatbotRules()->create(['question' => "Q{$i}", 'keywords' => ["k{$i}"], 'answer' => 'A']);
        }

        $this->actingAs($first->user)->get('/chatbot?instance='.$second->instance_id)->assertSee('3 / 5 entries used on your plan');

        $url = "/chatbot/{$second->instance_id}/rules";
        $this->actingAs($first->user)->post($url, $this->form(['question' => 'Four']))->assertSessionHasNoErrors();
        $this->actingAs($first->user)->post($url, $this->form(['question' => 'Five']))->assertSessionHasNoErrors();
        $this->actingAs($first->user)->post($url, $this->form(['question' => 'Six']))
            ->assertSessionHasErrors(['question' => 'Your plan allows 5 chatbot entries. Upgrade to add more.']);

        $this->assertDatabaseCount('chatbot_rules', 5);
        $this->actingAs($first->user)->get('/chatbot')->assertSee("You've used all your plan's chatbot entries.", false);
    }

    public function test_a_plan_without_the_chatbot(): void
    {
        Http::fake();
        $plan = Plan::factory()->create(['chatbot_entries' => 0]);
        $instance = $this->botInstance();
        $instance->user->subscription->update(['plan' => $plan->slug]);

        $this->actingAs($instance->user)->get('/chatbot')->assertSee("The chatbot isn't included in your plan.", false);
        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/rules", $this->form())->assertSessionHasErrors('question');
        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/toggle", ['enabled' => 1])->assertSessionHasErrors('enabled');

        // Already ON from before a downgrade: it stops replying.
        $this->receive($instance, 'price?');
        Http::assertNothingSent();
    }

    public function test_plan_cards_show_the_chatbot_limit(): void
    {
        $this->actingAs(User::factory()->create())->get('/billing')->assertSee('5 chatbot entries')->assertSee('1,000 chatbot entries');
        $this->assertSame('No chatbot', Plan::chatbotLabel(0));
        $this->assertSame('1 chatbot entry', Plan::chatbotLabel(1));
    }

    public function test_admins_can_set_a_plans_chatbot_limit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('slug', 'starter')->sole();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => $plan->name, 'description' => $plan->description, 'price' => $plan->price,
            'instances' => $plan->instances, 'messages_per_month' => $plan->messages_per_month, 'chatbot_entries' => 75,
        ])->assertRedirect(route('admin.plans.index'));

        $this->assertSame(75, $plan->fresh()->chatbot_entries);
        $this->actingAs($admin)->get(route('admin.plans.edit', $plan))->assertSee('Chatbot entries');
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

    // --- Business hours ----------------------------------------------------------------

    private function hours(array $overrides = []): array
    {
        // Mon–Sat, 10:00–19:00 India time.
        return array_merge(ChatbotHours::DEFAULTS, ['enabled' => true, 'message' => 'We are closed.'], $overrides);
    }

    private function at(string $indiaTime): void
    {
        $this->travelTo(Carbon::parse($indiaTime, 'Asia/Kolkata'));
    }

    public function test_business_hours_open_and_closed_times(): void
    {
        $hours = new ChatbotHours($this->hours());

        // 2026-10-05 is a Monday, 2026-10-11 a Sunday.
        $this->assertTrue($hours->isOpen(Carbon::parse('2026-10-05 10:00', 'Asia/Kolkata')));
        $this->assertTrue($hours->isOpen(Carbon::parse('2026-10-05 18:59', 'Asia/Kolkata')));
        $this->assertFalse($hours->isOpen(Carbon::parse('2026-10-05 19:00', 'Asia/Kolkata')));
        $this->assertFalse($hours->isOpen(Carbon::parse('2026-10-05 09:59', 'Asia/Kolkata')));
        $this->assertFalse($hours->isOpen(Carbon::parse('2026-10-11 12:00', 'Asia/Kolkata')));
        // The same moment in UTC (12:00 India = 06:30 UTC).
        $this->assertTrue($hours->isOpen(Carbon::parse('2026-10-05 06:30', 'UTC')));

        // Overnight: Friday 20:00 → Saturday 02:00 only.
        $night = new ChatbotHours($this->hours(['days' => [5], 'open' => '20:00', 'close' => '02:00']));
        $this->assertTrue($night->isOpen(Carbon::parse('2026-10-09 23:00', 'Asia/Kolkata')));
        $this->assertTrue($night->isOpen(Carbon::parse('2026-10-10 01:30', 'Asia/Kolkata')));
        $this->assertFalse($night->isOpen(Carbon::parse('2026-10-10 03:00', 'Asia/Kolkata')));
        $this->assertFalse($night->isOpen(Carbon::parse('2026-10-10 23:00', 'Asia/Kolkata')));

        // Off = always open.
        $this->assertTrue((new ChatbotHours(null))->isOpen(Carbon::parse('2026-10-11 03:00', 'Asia/Kolkata')));

        $this->assertSame('Mon–Sat, 10:00–19:00', $hours->summary());
        $this->assertSame('Mon, Wed, 10:00–19:00', (new ChatbotHours($this->hours(['days' => [3, 1]])))->summary());
    }

    public function test_business_hours_can_be_saved(): void
    {
        $instance = WhatsappSession::factory()->create();
        $url = "/chatbot/{$instance->instance_id}/hours";

        $this->actingAs($instance->user)->put($url, [
            'hours_enabled' => 1, 'days' => ['1', '2', '3'], 'open' => '09:00', 'close' => '18:00',
            'timezone' => 'Asia/Dubai', 'message' => 'Closed now.',
        ])->assertSessionHas('status', 'Business hours saved.');

        $hours = $instance->fresh()->chatbotHours();
        $this->assertTrue($hours->enabled);
        $this->assertSame([1, 2, 3], $hours->days);
        $this->assertSame('Asia/Dubai', $hours->timezone);
        $this->assertSame('Closed now.', $hours->message);

        $this->actingAs($instance->user)->get('/chatbot')->assertSee('Open Mon–Wed, 09:00–18:00 (Asia/Dubai)');

        $this->actingAs($instance->user)->put($url, ['hours_enabled' => 1, 'open' => '09:00', 'close' => '09:00', 'timezone' => 'Mars/Base', 'message' => ''])
            ->assertSessionHasErrors(['days', 'close', 'timezone', 'message']);

        $this->actingAs(User::factory()->create())->put($url, ['open' => '09:00', 'close' => '18:00', 'timezone' => 'UTC'])->assertNotFound();
    }

    public function test_outside_hours_an_unmatched_message_gets_the_closed_message_once(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'x'], 200)]);
        $instance = $this->botInstance();
        $instance->update(['chatbot_hours' => $this->hours()]);

        $this->at('2026-10-05 22:00'); // Monday night — closed
        $this->receive($instance, 'Hello, is anyone there?');

        $reply = Message::where('direction', 'outgoing')->sole();
        $this->assertSame('We are closed.', $reply->body);
        $this->assertSame('closed', $reply->bot_reply);

        // Not again for the same person within 12 hours…
        $this->at('2026-10-06 09:00');
        $this->receive($instance, 'Hello?');
        $this->assertSame(1, $this->outgoingCount());

        // …and a keyword is still answered while closed.
        $this->receive($instance, 'price?');
        $this->assertSame('Plans start at ₹499.', Message::where('direction', 'outgoing')->latest('id')->first()->body);
    }

    public function test_inside_hours_an_unmatched_message_gets_nothing(): void
    {
        Http::fake();
        $instance = $this->botInstance();
        $instance->update(['chatbot_hours' => $this->hours()]);

        $this->at('2026-10-05 12:00'); // Monday noon — open
        $this->receive($instance, 'Hello, is anyone there?');

        Http::assertNothingSent();
    }

    // --- Pause when the owner replies by hand ------------------------------------------

    /**
     * The worker reporting that the owner wrote to $to from their phone.
     */
    private function ownerReplies(WhatsappSession $instance, string $to = '919999999999', array $extra = []): void
    {
        config(['worker.secret' => self::SECRET]);

        $this->postJson('/internal/worker/events', array_merge([
            'event' => 'message.sent_from_phone',
            'instance_id' => $instance->instance_id,
            'to' => $to,
            'to_is_lid' => false,
            'whatsapp_message_id' => 'WA-PHONE-'.uniqid(),
        ], $extra), ['X-Internal-Secret' => self::SECRET])->assertNoContent();
    }

    public function test_the_bot_pauses_in_a_chat_after_the_owner_replies_there(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'x'], 200)]);
        $instance = $this->botInstance(); // default pause: 30 minutes

        $this->ownerReplies($instance);

        // Paused in that chat…
        $this->receive($instance, 'price?');
        $this->assertSame(0, $this->outgoingCount());

        // …but not in another one.
        $this->receive($instance, 'price?', ['from' => '918888888888']);
        $this->assertSame(1, $this->outgoingCount());

        $this->actingAs($instance->user)->get('/chatbot')->assertSee('Paused right now')->assertSee('+919999999999');

        // Answers again once the 30 minutes are over.
        $this->travel(31)->minutes();
        $this->receive($instance, 'price?');
        $this->assertSame(2, $this->outgoingCount());
    }

    public function test_each_owner_reply_moves_the_pause_forward(): void
    {
        $this->freezeSecond(); // the database stores whole seconds
        $instance = $this->botInstance();

        $this->ownerReplies($instance);
        $this->travel(20)->minutes();
        $this->ownerReplies($instance);

        $this->assertDatabaseCount('chatbot_pauses', 1);
        $this->assertTrue($instance->chatbotPauses()->sole()->paused_until->equalTo(now()->addMinutes(30)));
    }

    public function test_no_pause_for_our_own_messages_a_lid_or_when_switched_off(): void
    {
        $instance = $this->botInstance();
        Message::factory()->for($instance, 'whatsappSession')->create(['whatsapp_message_id' => 'WA-OURS']);

        $this->ownerReplies($instance, extra: ['whatsapp_message_id' => 'WA-OURS']); // sent by the API / bot
        $this->ownerReplies($instance, '248600000000055', ['to_is_lid' => true]);

        $instance->update(['chatbot_pause_minutes' => 0]);
        $this->ownerReplies($instance);

        $this->assertDatabaseCount('chatbot_pauses', 0);
    }

    public function test_the_pause_length_can_be_changed_and_a_pause_ended_early(): void
    {
        $instance = $this->botInstance();

        $this->actingAs($instance->user)->put("/chatbot/{$instance->instance_id}/pause", ['pause_minutes' => 120])
            ->assertSessionHas('status', 'The bot will pause for 2 hours after you reply yourself.');
        $this->assertSame(120, $instance->fresh()->chatbot_pause_minutes);

        $this->actingAs($instance->user)->put("/chatbot/{$instance->instance_id}/pause", ['pause_minutes' => 7])
            ->assertSessionHasErrors('pause_minutes');

        $this->ownerReplies($instance);
        $pause = $instance->chatbotPauses()->sole();

        $this->actingAs(User::factory()->create())->delete("/chatbot/{$instance->instance_id}/pauses/{$pause->id}")->assertNotFound();
        $this->actingAs($instance->user)->delete("/chatbot/{$instance->instance_id}/pauses/{$pause->id}")
            ->assertSessionHas('status', 'The bot is answering in that chat again.');
        $this->assertDatabaseCount('chatbot_pauses', 0);
    }

    // --- Stats -----------------------------------------------------------------------

    public function test_the_page_shows_reply_stats(): void
    {
        $instance = WhatsappSession::factory()->create();
        $used = $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price'], 'answer' => 'A']);
        $instance->chatbotRules()->create(['question' => 'Unused', 'keywords' => ['x'], 'answer' => 'B']);
        $reply = fn (array $attributes) => Message::factory()->for($instance, 'whatsappSession')->create($attributes);

        $reply(['chatbot_rule_id' => $used->id, 'bot_reply' => 'answer', 'status' => 'sent']);
        $reply(['chatbot_rule_id' => $used->id, 'bot_reply' => 'answer', 'status' => 'read']);
        $reply(['chatbot_rule_id' => $used->id, 'bot_reply' => 'answer', 'status' => 'failed']); // not counted
        $reply(['chatbot_rule_id' => $used->id, 'bot_reply' => 'answer', 'status' => 'sent', 'created_at' => now()->subDays(10)]);
        $reply(['bot_reply' => 'closed', 'status' => 'sent']);
        $reply(['status' => 'sent']); // not a bot reply

        $this->actingAs($instance->user)->get('/chatbot')->assertOk()
            ->assertSeeInOrder(['Answers sent · 7 days', '2', '"Closed" messages · 7 days', '1', 'All bot replies · 30 days', '4'])
            ->assertSee('Replied 3 times · 2 in 7 days')
            ->assertSee('Not used yet');
    }

    // --- Attachments -----------------------------------------------------------------

    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    public function test_an_entry_can_have_a_file_detected_from_its_content(): void
    {
        Storage::fake('whatsapp_media');
        $instance = WhatsappSession::factory()->create();

        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/rules", $this->form([
            'media' => UploadedFile::fake()->createWithContent('Price list.pdf', self::PDF),
        ]))->assertSessionHasNoErrors();

        $rule = ChatbotRule::sole();
        $this->assertSame('document', $rule->media_type);
        $this->assertSame('application/pdf', $rule->media_mime_type);
        $this->assertSame('Price list.pdf', $rule->media_file_name);
        $this->assertStringStartsWith("{$instance->instance_id}/out-", $rule->media_path);
        Storage::disk('whatsapp_media')->assertExists($rule->media_path);

        $this->actingAs($instance->user)->get('/chatbot')->assertSee('Price list.pdf');

        // A photo is sent as an image, whatever its file name says.
        $this->actingAs($instance->user)->put("/chatbot/{$instance->instance_id}/rules/{$rule->id}", $this->form([
            'media' => UploadedFile::fake()->createWithContent('menu.pdf', self::JPEG),
        ]))->assertSessionHasNoErrors();
        $this->assertSame('image', $rule->fresh()->media_type);
        $this->assertSame('image/jpeg', $rule->fresh()->media_mime_type);
    }

    public function test_a_file_can_be_removed_and_a_bad_one_is_rejected(): void
    {
        Storage::fake('whatsapp_media');
        $instance = WhatsappSession::factory()->create();
        $url = "/chatbot/{$instance->instance_id}/rules";

        $this->actingAs($instance->user)->post($url, $this->form([
            'media' => UploadedFile::fake()->createWithContent('empty.pdf', ''),
        ]))->assertSessionHasErrors('media');
        $this->assertDatabaseCount('chatbot_rules', 0);

        $this->actingAs($instance->user)->post($url, $this->form([
            'media' => UploadedFile::fake()->createWithContent('offer.jpg', self::JPEG),
        ]));
        $rule = ChatbotRule::sole();

        // Saving without a new file keeps the old one...
        $this->actingAs($instance->user)->put("{$url}/{$rule->id}", $this->form());
        $this->assertSame('image', $rule->fresh()->media_type);

        // ..."Remove" goes back to text only.
        $this->actingAs($instance->user)->put("{$url}/{$rule->id}", $this->form(['remove_media' => 1]));
        $this->assertNull($rule->fresh()->media_type);
        $this->assertNull($rule->fresh()->media_path);
    }

    public function test_a_matching_message_gets_the_answer_with_its_file(): void
    {
        Storage::fake('whatsapp_media');
        Http::fake(['*' => Http::response(['message_id' => 'WA-BOT-1'], 200)]);
        $instance = $this->botInstance();
        Storage::disk('whatsapp_media')->put("{$instance->instance_id}/out-x.pdf", self::PDF);
        $instance->chatbotRules()->first()->update([
            'media_type' => 'document', 'media_path' => "{$instance->instance_id}/out-x.pdf",
            'media_mime_type' => 'application/pdf', 'media_file_name' => 'Prices.pdf', 'media_size' => 70,
        ]);

        $this->receive($instance, 'price?');

        $reply = Message::where('direction', 'outgoing')->sole();
        $this->assertSame('document', $reply->type);
        $this->assertSame('Plans start at ₹499.', $reply->body);
        $this->assertSame("{$instance->instance_id}/out-x.pdf", $reply->media_path);
        Http::assertSent(fn ($request) => $request['type'] === 'document'
            && $request['message'] === 'Plans start at ₹499.'
            && $request['media']['file_name'] === 'Prices.pdf');
    }

    public function test_a_missing_file_still_sends_the_answer_as_text(): void
    {
        Storage::fake('whatsapp_media');
        Http::fake(['*' => Http::response(['message_id' => 'WA-BOT-1'], 200)]);
        $instance = $this->botInstance();
        $instance->chatbotRules()->first()->update([
            'media_type' => 'image', 'media_path' => "{$instance->instance_id}/gone.jpg", 'media_mime_type' => 'image/jpeg', 'media_size' => 10,
        ]);

        $this->receive($instance, 'price?');

        $this->assertSame('text', Message::where('direction', 'outgoing')->sole()->type);
    }

    public function test_the_file_is_shown_to_its_owner_only(): void
    {
        Storage::fake('whatsapp_media');
        $instance = WhatsappSession::factory()->create();
        Storage::disk('whatsapp_media')->put("{$instance->instance_id}/out-x.jpg", self::JPEG);
        $rule = $instance->chatbotRules()->create(['question' => 'Menu', 'keywords' => ['menu'], 'answer' => 'Here',
            'media_type' => 'image', 'media_path' => "{$instance->instance_id}/out-x.jpg", 'media_mime_type' => 'image/jpeg', 'media_size' => 20]);
        $url = "/chatbot/{$instance->instance_id}/rules/{$rule->id}/media";

        $this->actingAs($instance->user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    }

    // --- CSV import / export ---------------------------------------------------------

    private function importCsv(WhatsappSession $instance, string $csv)
    {
        return $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/import", [
            'csv' => UploadedFile::fake()->createWithContent('entries.csv', $csv),
        ]);
    }

    public function test_entries_export_as_csv_in_list_order(): void
    {
        $instance = WhatsappSession::factory()->create(['name' => 'My Shop']);
        $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price', 'cost'], 'answer' => "From ₹499.\nSee the site.", 'position' => 2]);
        $instance->chatbotRules()->create(['question' => 'Timings', 'keywords' => ['open'], 'answer' => '10 to 7', 'position' => 1, 'enabled' => false]);

        $response = $this->actingAs($instance->user)->get("/chatbot/{$instance->instance_id}/export")->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="chatbot-my-shop.csv"');

        $this->assertSame(
            "\xEF\xBB\xBFquestion,keywords,answer,status\nTimings,open,\"10 to 7\",off\nPrices,\"price, cost\",\"From ₹499.\nSee the site.\",on\n",
            $response->getContent(),
        );
    }

    public function test_an_export_can_be_imported_back_into_another_instance(): void
    {
        $from = WhatsappSession::factory()->create();
        $from->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price', 'cost'], 'answer' => "From ₹499.\nSee the site.", 'position' => 1]);
        $from->chatbotRules()->create(['question' => 'Timings', 'keywords' => ['open'], 'answer' => '10 to 7', 'position' => 2, 'enabled' => false]);
        $csv = $this->actingAs($from->user)->get("/chatbot/{$from->instance_id}/export")->getContent();

        $to = WhatsappSession::factory()->for($from->user)->create();
        $this->importCsv($to, $csv)->assertSessionHasNoErrors()->assertSessionHas('status', 'Import done: added 2 entries.');

        $rules = $to->chatbotRules()->get();
        $this->assertSame(['Prices', 'Timings'], $rules->pluck('question')->all());
        $this->assertSame(['price', 'cost'], $rules[0]->keywords);
        $this->assertSame("From ₹499.\nSee the site.", $rules[0]->answer);
        $this->assertSame([true, false], $rules->pluck('enabled')->all());
        $this->assertSame([1, 2], $rules->pluck('position')->all());
    }

    public function test_a_matching_question_updates_the_entry(): void
    {
        $instance = WhatsappSession::factory()->create();
        $rule = $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price'], 'answer' => 'Old', 'position' => 1, 'enabled' => false]);

        // Any column order, ";" from European Excel, no status column.
        $this->importCsv($instance, "Answer;Question;Keywords\nNew;PRICES;price, rate\nWe deliver;Delivery;deliver\n")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Import done: added 1 entry and updated 1.');

        $rule->refresh();
        $this->assertSame('New', $rule->answer);
        $this->assertSame(['price', 'rate'], $rule->keywords);
        $this->assertFalse($rule->enabled); // no status given: unchanged
        $this->assertSame(2, $instance->chatbotRules()->count());
    }

    public function test_a_tab_separated_file_from_excel_imports(): void
    {
        $instance = WhatsappSession::factory()->create();

        // What Excel's "Text (Tab delimited)" save gives, even when named .csv.
        $this->importCsv($instance, "question\tkeywords\tanswer\tstatus\r\nPrices\t\"price, cost, rate\"\tPlans start at 499\tOn\r\nDiwali Offer\t\"offer, discount\"\t20% off\tOff\r\n")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Import done: added 2 entries.');

        $rules = $instance->chatbotRules()->get();
        $this->assertSame(['price', 'cost', 'rate'], $rules[0]->keywords);
        $this->assertSame([true, false], $rules->pluck('enabled')->all());
    }

    public function test_a_bad_row_imports_nothing(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->importCsv($instance, "question,keywords,answer,status\nGood,ok,Fine,on\n,x,No question,on\nNo keywords,,Text,on\nBad status,y,Z,maybe\nGood,again,Dup,on\n")
            ->assertSessionHasErrors(['csv' => 'Row 3: the question is empty.']);

        $errors = session('errors')->get('csv');
        $this->assertSame([
            'Row 3: the question is empty.',
            'Row 4: add at least one keyword.',
            'Row 5: status must be "on" or "off".',
            'Row 6: the same question is already on row 2.',
        ], $errors);
        $this->assertDatabaseCount('chatbot_rules', 0);

        $this->importCsv($instance, "name,phone\nA,1\n")->assertSessionHasErrors('csv');
        $this->importCsv($instance, "question,keywords,answer\n")->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('chatbot_rules', 0);
    }

    public function test_an_import_must_fit_the_plan(): void
    {
        // Free plan: 5 chatbot entries; 4 used.
        $instance = WhatsappSession::factory()->create();
        for ($i = 1; $i <= 4; $i++) {
            $instance->chatbotRules()->create(['question' => "Existing {$i}", 'keywords' => ["e{$i}"], 'answer' => 'E']);
        }

        $this->importCsv($instance, "question,keywords,answer\nA,a,A\nB,b,B\n")
            ->assertSessionHasErrors(['csv' => 'The file has 2 new entries, but your plan has room for 1 more. Remove some rows, or upgrade your plan.']);
        $this->assertSame(4, $instance->chatbotRules()->count());

        // Updating existing entries doesn't use up room: this fits.
        $this->importCsv($instance, "question,keywords,answer\nExisting 1,e,Changed\nA,a,A\n")->assertSessionHasNoErrors();
        $this->assertSame(5, $instance->chatbotRules()->count());
    }

    public function test_another_users_instance_cant_be_exported_or_imported(): void
    {
        $instance = WhatsappSession::factory()->create();
        $instance->chatbotRules()->create(['question' => 'Secret', 'keywords' => ['s'], 'answer' => 'S']);
        $other = User::factory()->create();

        $this->actingAs($other)->get("/chatbot/{$instance->instance_id}/export")->assertNotFound();
        $this->actingAs($other)->post("/chatbot/{$instance->instance_id}/import", [
            'csv' => UploadedFile::fake()->createWithContent('e.csv', "question,keywords,answer\nA,a,A\n"),
        ])->assertNotFound();
        $this->assertSame(1, $instance->chatbotRules()->count());
    }

    // --- Per-entry ON/OFF ------------------------------------------------------------

    public function test_an_entry_can_be_switched_off_and_on(): void
    {
        $instance = WhatsappSession::factory()->create();
        $rule = $instance->chatbotRules()->create(['question' => 'Diwali offer', 'keywords' => ['offer'], 'answer' => '20% off']);
        $url = "/chatbot/{$instance->instance_id}/rules/{$rule->id}/toggle";

        $this->assertTrue($rule->fresh()->enabled);

        $this->actingAs($instance->user)->post($url, ['enabled' => 0])
            ->assertRedirect(route('chatbot.index', ['instance' => $instance->instance_id]).'#rule-'.$rule->id);
        $this->assertFalse($rule->fresh()->enabled);
        $this->actingAs($instance->user)->get('/chatbot')->assertSee('Diwali offer')->assertSee('OFF');

        $this->actingAs($instance->user)->post($url, ['enabled' => 1]);
        $this->assertTrue($rule->fresh()->enabled);
    }

    public function test_a_switched_off_entry_never_answers(): void
    {
        Http::fake();
        $instance = $this->botInstance();
        $instance->chatbotRules()->update(['enabled' => false]);

        $this->receive($instance, 'What is the price?');

        $this->assertSame(0, $this->outgoingCount());
        Http::assertNothingSent();
    }

    public function test_the_next_best_entry_answers_when_the_best_is_off(): void
    {
        $instance = WhatsappSession::factory()->create();
        $instance->chatbotRules()->create(['question' => 'Shoe prices', 'keywords' => ['shoes', 'price'], 'answer' => 'A', 'enabled' => false]);
        $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price'], 'answer' => 'B']);

        $this->assertSame('Prices', ChatbotRule::bestMatch($instance->chatbotRules()->get(), 'price of shoes?')->question);
    }

    public function test_the_test_box_says_when_a_switched_off_entry_would_match(): void
    {
        $instance = WhatsappSession::factory()->create();
        $instance->chatbotRules()->create(['question' => 'Diwali offer', 'keywords' => ['offer'], 'answer' => '20% off', 'enabled' => false]);

        $this->actingAs($instance->user)->followingRedirects()
            ->post("/chatbot/{$instance->instance_id}/test", ['test_message' => 'any offer?'])
            ->assertSee('No keyword matched')
            ->assertSee("would match, but it's switched off", false);
    }

    public function test_the_bot_needs_an_entry_that_is_on(): void
    {
        $instance = WhatsappSession::factory()->create();
        $instance->chatbotRules()->create(['question' => 'Q', 'keywords' => ['q'], 'answer' => 'A', 'enabled' => false]);

        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/toggle", ['enabled' => 1])->assertSessionHasErrors('enabled');
        $this->assertFalse($instance->fresh()->chatbot_enabled);
    }

    public function test_another_users_entry_cant_be_switched(): void
    {
        $instance = WhatsappSession::factory()->create();
        $rule = $instance->chatbotRules()->create(['question' => 'Q', 'keywords' => ['q'], 'answer' => 'A']);

        $this->actingAs(User::factory()->create())
            ->post("/chatbot/{$instance->instance_id}/rules/{$rule->id}/toggle", ['enabled' => 0])
            ->assertNotFound();
        $this->assertTrue($rule->fresh()->enabled);
    }

    // --- Order -----------------------------------------------------------------------

    public function test_entries_can_be_moved_and_the_order_decides_a_tie(): void
    {
        $instance = WhatsappSession::factory()->create();
        $base = "/chatbot/{$instance->instance_id}/rules";

        $this->actingAs($instance->user)->post($base, $this->form(['question' => 'Shoes', 'keywords' => 'shoes']));
        $this->actingAs($instance->user)->post($base, $this->form(['question' => 'Stock', 'keywords' => 'have']));
        $this->actingAs($instance->user)->post($base, $this->form(['question' => 'Third', 'keywords' => 'third']));
        [$shoes, $stock, $third] = $instance->chatbotRules()->get()->all();
        $this->assertSame([1, 2, 3], [$shoes->position, $stock->position, $third->position]);

        // A tie: the higher one wins.
        $this->assertSame('Shoes', ChatbotRule::bestMatch($instance->chatbotRules()->get(), 'do you have shoes?')->question);

        $this->actingAs($instance->user)->post("{$base}/{$stock->id}/move", ['direction' => 'up'])
            ->assertRedirect(route('chatbot.index', ['instance' => $instance->instance_id]).'#rule-'.$stock->id);

        $this->assertSame(['Stock', 'Shoes', 'Third'], $instance->chatbotRules()->pluck('question')->all());
        $this->assertSame('Stock', ChatbotRule::bestMatch($instance->chatbotRules()->get(), 'do you have shoes?')->question);
        $this->actingAs($instance->user)->get('/chatbot')->assertSeeInOrder(['1. Stock', '2. Shoes', '3. Third']);

        // Already at the edge: nothing changes.
        $this->actingAs($instance->user)->post("{$base}/{$stock->id}/move", ['direction' => 'up']);
        $this->actingAs($instance->user)->post("{$base}/{$third->id}/move", ['direction' => 'down']);
        $this->assertSame(['Stock', 'Shoes', 'Third'], $instance->chatbotRules()->pluck('question')->all());

        $this->actingAs($instance->user)->post("{$base}/{$shoes->id}/move", ['direction' => 'down']);
        $this->assertSame(['Stock', 'Third', 'Shoes'], $instance->chatbotRules()->pluck('question')->all());

        $this->actingAs($instance->user)->post("{$base}/{$shoes->id}/move", ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_another_users_entry_cant_be_moved(): void
    {
        $instance = WhatsappSession::factory()->create();
        $rule = $instance->chatbotRules()->create(['question' => 'Q', 'keywords' => ['q'], 'answer' => 'A']);

        $this->actingAs(User::factory()->create())
            ->post("/chatbot/{$instance->instance_id}/rules/{$rule->id}/move", ['direction' => 'up'])
            ->assertNotFound();
    }

    // --- Unanswered questions --------------------------------------------------------

    public function test_the_page_lists_recent_messages_no_entry_answers(): void
    {
        $instance = WhatsappSession::factory()->create();
        $instance->chatbotRules()->create(['question' => 'Prices', 'keywords' => ['price'], 'answer' => 'A']);
        $incoming = fn (array $attributes) => Message::factory()->for($instance, 'whatsappSession')
            ->create(['direction' => 'incoming', 'type' => 'text', 'from_number' => '919999999999', ...$attributes]);

        $incoming(['body' => 'do you  deliver to pune?']);
        $incoming(['body' => 'Do you deliver to Pune?']); // same question, counted once (newest wording shown)
        $incoming(['body' => 'Price for two kg?']); // an entry answers it
        $incoming(['body' => 'Are you open on Sunday?', 'created_at' => now()->subDays(8)]); // too old
        $incoming(['body' => 'Old outgoing', 'direction' => 'outgoing']);
        $incoming(['body' => '', 'type' => 'audio']);

        $this->actingAs($instance->user)->get('/chatbot')->assertOk()
            ->assertSee('Unanswered questions')
            ->assertSee('Do you deliver to Pune?')
            ->assertSee('asked 2 times')
            ->assertDontSee('Price for two kg?')
            ->assertDontSee('Are you open on Sunday?')
            ->assertDontSee('Old outgoing');

        // Once an entry matches, it's gone from the list.
        $instance->chatbotRules()->create(['question' => 'Delivery', 'keywords' => ['deliver'], 'answer' => 'B']);
        $this->actingAs($instance->user)->get('/chatbot')->assertDontSee('Do you deliver to Pune?');
    }

    public function test_add_as_entry_prefills_the_question(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->actingAs($instance->user)
            ->get(route('chatbot.index', ['instance' => $instance->instance_id, 'question' => 'Do you deliver to Pune?']))
            ->assertOk()
            ->assertSee('value="Do you deliver to Pune?"', false);
    }

    public function test_another_users_messages_are_never_listed(): void
    {
        $mine = WhatsappSession::factory()->create();
        $theirs = WhatsappSession::factory()->create();
        Message::factory()->for($theirs, 'whatsappSession')
            ->create(['direction' => 'incoming', 'type' => 'text', 'body' => 'Secret question from their customer']);

        $this->actingAs($mine->user)->get(route('chatbot.index', ['instance' => $theirs->instance_id]))
            ->assertOk()
            ->assertDontSee('Secret question from their customer');
    }

    public function test_hours_alone_let_the_bot_be_switched_on(): void
    {
        $instance = WhatsappSession::factory()->create(['chatbot_hours' => $this->hours()]);

        $this->actingAs($instance->user)->post("/chatbot/{$instance->instance_id}/toggle", ['enabled' => 1]);

        $this->assertTrue($instance->fresh()->chatbot_enabled);
    }
}
