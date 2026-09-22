<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The pages use @vite(); skip loading the built CSS/JS files in tests.
        $this->withoutVite();
    }

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_guest_cannot_access_instances(): void
    {
        $this->get('/instances')->assertRedirect(route('login'));
    }

    public function test_user_can_create_an_instance(): void
    {
        Http::fake(['*' => Http::response(['started' => true], 202)]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/instances', ['name' => 'My Business WhatsApp']);

        $instance = WhatsappSession::where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('instances.show', $instance));
        $this->assertSame('My Business WhatsApp', $instance->name);
        $this->assertSame('connecting', $instance->status);
        $this->assertTrue(Str::isUuid($instance->instance_id));

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3001/sessions'
            && $request['instance_id'] === $instance->instance_id
            && $request->hasHeader('X-Internal-Secret'));
    }

    public function test_instance_creation_shows_a_friendly_error_when_the_worker_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/instances', ['name' => 'My Instance']);

        $instance = WhatsappSession::where('user_id', $user->id)->firstOrFail();

        // The row still exists — the user can retry from the show page —
        // it just couldn't reach the worker to actually start a session.
        $response->assertRedirect(route('instances.show', $instance));
        $response->assertSessionHas('error');
    }

    public function test_index_page_renders_for_the_owner(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->get(route('instances.index'))
            ->assertOk()
            ->assertSee($instance->name);
    }

    public function test_index_page_renders_when_the_user_has_no_instances(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('instances.index'))
            ->assertOk()
            ->assertSee('No instances yet');
    }

    public function test_create_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('instances.create'))->assertOk();
    }

    public function test_show_page_renders_with_a_qr_code(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create([
            'status' => 'qr_pending',
            'qr_code' => 'data:image/png;base64,fakeqrdata',
        ]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('data:image/png;base64,fakeqrdata', escape: false);
    }

    public function test_show_page_renders_when_connected(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee($instance->phone_number);
    }

    public function test_show_page_lists_recent_messages(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'incoming',
            'from_number' => '919999999999',
            'body' => 'A message only this test can see',
        ]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('A message only this test can see')
            ->assertSee('919999999999');
    }

    public function test_show_page_says_no_messages_yet_when_there_are_none(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('No messages yet');
    }

    public function test_show_page_renders_when_disconnected(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create(['status' => 'disconnected']);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('Reconnect');
    }

    public function test_user_cannot_view_another_users_instance(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('instances.show', $instance))->assertNotFound();
    }

    public function test_user_cannot_see_another_users_instance_status(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('instances.status', $instance))->assertNotFound();
    }

    public function test_user_cannot_disconnect_another_users_instance(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->connected()->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete(route('instances.destroy', $instance))->assertNotFound();
        $this->assertSame('connected', $instance->fresh()->status);
    }

    public function test_status_endpoint_returns_the_instances_current_state(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->getJson(route('instances.status', $instance))
            ->assertOk()
            ->assertJson([
                'status' => 'connected',
                'phone_number' => $instance->phone_number,
            ]);
    }

    public function test_disconnect_calls_the_worker_and_updates_status(): void
    {
        Http::fake(['*' => Http::response(['stopped' => true], 200)]);

        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->delete(route('instances.destroy', $instance))
            ->assertRedirect(route('instances.index'));

        $this->assertSame('disconnected', $instance->fresh()->status);

        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:3001/sessions/{$instance->instance_id}"
            && $request->method() === 'DELETE');
    }

    public function test_reconnect_calls_the_worker_and_resets_status(): void
    {
        Http::fake(['*' => Http::response(['started' => true], 202)]);

        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create([
            'status' => 'disconnected',
            'qr_code' => 'data:image/png;base64,stale',
        ]);

        $this->actingAs($user)->post(route('instances.reconnect', $instance))
            ->assertRedirect(route('instances.show', $instance));

        $instance->refresh();
        $this->assertSame('connecting', $instance->status);
        $this->assertNull($instance->qr_code);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3001/sessions');
    }

    public function test_owner_can_send_a_test_message_from_the_dashboard(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-DASH-1'], 200)]);

        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.send-test-message', $instance), [
            'to' => '919999999999',
            'message' => 'Hello from the dashboard',
        ])->assertRedirect(route('instances.show', $instance));

        $sent = Message::where('whatsapp_session_id', $instance->id)->firstOrFail();
        $this->assertSame('sent', $sent->status);
        $this->assertSame('WA-DASH-1', $sent->whatsapp_message_id);

        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:3001/sessions/{$instance->instance_id}/messages");
    }

    public function test_sending_a_test_message_shows_an_error_when_the_worker_fails(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.send-test-message', $instance), [
            'to' => '919999999999',
            'message' => 'Hello from the dashboard',
        ])->assertRedirect(route('instances.show', $instance))
            ->assertSessionHas('error');

        $this->assertSame('failed', Message::where('whatsapp_session_id', $instance->id)->firstOrFail()->status);
    }

    public function test_cannot_send_a_test_message_when_not_connected(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create(['status' => 'disconnected']);

        $this->actingAs($user)->post(route('instances.send-test-message', $instance), [
            'to' => '919999999999',
            'message' => 'Hello',
        ])->assertStatus(422);

        $this->assertSame(0, Message::where('whatsapp_session_id', $instance->id)->count());
    }

    public function test_user_cannot_send_a_test_message_on_another_users_instance(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->connected()->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('instances.send-test-message', $instance), [
            'to' => '919999999999',
            'message' => 'Hello',
        ])->assertNotFound();

        $this->assertSame(0, Message::where('whatsapp_session_id', $instance->id)->count());
    }
}
