<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstancePageTest extends TestCase
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

    public function test_recent_messages_show_ten_per_page_newest_first(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        foreach (range(1, 12) as $i) {
            Message::factory()->for($instance, 'whatsappSession')->create([
                'body' => sprintf('Numbered message %02d', $i),
                'created_at' => now()->subMinutes(100 - $i), // #12 is the newest
            ]);
        }

        $page1 = $this->actingAs($user)->get(route('instances.show', $instance))->assertOk();
        $page1->assertSee('Numbered message 12')
            ->assertSee('Numbered message 03')
            ->assertDontSee('Numbered message 02')
            ->assertDontSee('Numbered message 01')
            ->assertSeeInOrder(['Showing', '1', 'to', '10', 'of', '12', 'results'])
            ->assertSee('messages_page=2', escape: false)
            ->assertSee('#recent-messages', escape: false);

        $this->actingAs($user)->get(route('instances.show', ['instance' => $instance, 'messages_page' => 2]))
            ->assertOk()
            ->assertSee('Numbered message 02')
            ->assertSee('Numbered message 01')
            ->assertDontSee('Numbered message 03')
            ->assertSeeInOrder(['Showing', '11', 'to', '12', 'of', '12', 'results']);
    }

    public function test_no_pagination_bar_with_ten_or_fewer_messages(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->count(3)->for($instance, 'whatsappSession')->create();

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertDontSee('messages_page=', escape: false);
    }

    public function test_view_all_links_to_this_instances_messages(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee(route('messages.index', ['instance_id' => $instance->instance_id]), escape: false);
    }

    public function test_connected_page_shows_the_banner_send_form_and_file_preview(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('Your WhatsApp is ready to use')
            ->assertSee('Send a test message')
            ->assertSee('data-preview', escape: false)
            ->assertSee('A preview of your file appears here');
    }

    public function test_disconnected_page_has_no_send_form(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create(['status' => 'disconnected']);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('Not connected')
            ->assertSee('Reconnect')
            ->assertDontSee('Send a test message');
    }

    public function test_webhook_card_shows_active_or_failed_state(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertSee('Webhook is active');

        $instance->webhookDeliveries()->create([
            'event' => 'message.received', 'url' => $instance->webhook_url, 'status' => 'failed',
        ]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertSee('Last delivery failed')
            ->assertDontSee('Webhook is active');
    }
}
