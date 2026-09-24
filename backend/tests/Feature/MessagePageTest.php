<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessagePageTest extends TestCase
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

    public function test_guest_cannot_view_messages(): void
    {
        $this->get(route('messages.index'))->assertRedirect(route('login'));
    }

    public function test_empty_state_when_there_are_no_messages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('No messages yet');
    }

    public function test_shows_sent_and_received_messages_including_test_button_sends(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create(['name' => 'Shop phone']);

        // No api_token_id — a dashboard test-button send still belongs here.
        Message::factory()->for($instance, 'whatsappSession')->create([
            'body' => 'Sent from the dashboard button',
            'to_number' => '919111111111',
        ]);
        Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'incoming',
            'status' => 'received',
            'to_number' => null,
            'from_number' => '919222222222',
            'body' => 'A reply from the customer',
        ]);

        $this->actingAs($user)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Shop phone')
            ->assertSee('Sent from the dashboard button')
            ->assertSee('919111111111')
            ->assertSee('A reply from the customer')
            ->assertSee('919222222222');
    }

    public function test_never_shows_another_users_messages(): void
    {
        $user = User::factory()->create();
        WhatsappSession::factory()->for($user)->connected()->create();

        $otherInstance = WhatsappSession::factory()->connected()->create();
        Message::factory()->for($otherInstance, 'whatsappSession')->create(['body' => 'Someone else’s secret']);

        $this->actingAs($user)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('No messages yet')
            ->assertDontSee('Someone else’s secret');
    }

    public function test_filtering_by_another_users_instance_id_shows_nothing(): void
    {
        $user = User::factory()->create();
        $otherInstance = WhatsappSession::factory()->connected()->create();
        Message::factory()->for($otherInstance, 'whatsappSession')->create(['body' => 'Someone else’s secret']);

        $this->actingAs($user)->get(route('messages.index', ['instance_id' => $otherInstance->instance_id]))
            ->assertOk()
            ->assertSee('No messages match these filters')
            ->assertDontSee('Someone else’s secret');
    }

    public function test_can_filter_by_instance(): void
    {
        $user = User::factory()->create();
        $instanceA = WhatsappSession::factory()->for($user)->connected()->create();
        $instanceB = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->for($instanceA, 'whatsappSession')->create(['body' => 'From instance A']);
        Message::factory()->for($instanceB, 'whatsappSession')->create(['body' => 'From instance B']);

        $this->actingAs($user)->get(route('messages.index', ['instance_id' => $instanceA->instance_id]))
            ->assertOk()
            ->assertSee('From instance A')
            ->assertDontSee('From instance B');
    }

    public function test_can_filter_by_direction(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->for($instance, 'whatsappSession')->create(['body' => 'An outgoing one']);
        Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'incoming', 'status' => 'received', 'body' => 'An incoming one',
        ]);

        $this->actingAs($user)->get(route('messages.index', ['direction' => 'incoming']))
            ->assertOk()
            ->assertSee('An incoming one')
            ->assertDontSee('An outgoing one');
    }

    public function test_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->for($instance, 'whatsappSession')->create(['status' => 'sent', 'body' => 'This one worked']);
        Message::factory()->for($instance, 'whatsappSession')->create(['status' => 'failed', 'body' => 'This one failed']);

        $this->actingAs($user)->get(route('messages.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('This one failed')
            ->assertDontSee('This one worked');
    }

    public function test_unknown_filter_values_are_ignored(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->for($instance, 'whatsappSession')->create(['body' => 'Still visible']);

        $this->actingAs($user)->get(route('messages.index', ['direction' => 'sideways', 'status' => 'bogus']))
            ->assertOk()
            ->assertSee('Still visible');
    }

    public function test_internal_error_text_is_never_shown(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->for($instance, 'whatsappSession')->create([
            'status' => 'failed',
            'error' => 'Baileys: connection reset by peer at line 42',
        ]);

        $this->actingAs($user)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Failed')
            ->assertDontSee('connection reset by peer', escape: false);
    }

    public function test_shows_twenty_messages_per_page(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        foreach (range(1, 25) as $i) {
            Message::factory()->for($instance, 'whatsappSession')->create([
                'body' => sprintf('Paged message %02d', $i),
                'created_at' => now()->subMinutes(100 - $i), // #25 is the newest
            ]);
        }

        $this->actingAs($user)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Paged message 25')
            ->assertSee('Paged message 06')
            ->assertDontSee('Paged message 05')
            ->assertSeeInOrder(['Showing', '1', 'to', '20', 'of', '25', 'results'])
            ->assertSee('page=2', escape: false);

        $this->actingAs($user)->get(route('messages.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Paged message 05')
            ->assertSee('Paged message 01')
            ->assertDontSee('Paged message 06');
    }

    public function test_regular_user_sees_the_messages_link_in_the_sidebar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('messages.index'), escape: false);
    }
}
