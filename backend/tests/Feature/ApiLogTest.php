<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiLogTest extends TestCase
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

    public function test_guest_cannot_view_api_logs(): void
    {
        $this->get(route('api-logs.index'))->assertRedirect(route('login'));
    }

    public function test_empty_state_when_no_api_calls_have_been_made(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSee('No API calls logged yet');
    }

    public function test_a_real_api_call_appears_in_the_log_with_a_masked_token_and_matching_response(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create(['phone_number' => '919000000000']);
        ['token' => $token, 'plainText' => $plainText] = ApiToken::generateFor($instance, 'My server');

        $message = $instance->messages()->create([
            'api_token_id' => $token->id,
            'direction' => 'outgoing',
            'to_number' => '919999999999',
            'body' => 'Hello there',
            'status' => 'sent',
            'whatsapp_message_id' => 'WA-LOG-1',
        ]);

        $response = $this->actingAs($user)->get(route('api-logs.index'));

        $response->assertOk()
            ->assertSee('919000000000') // From Number
            ->assertSee($instance->instance_id, escape: false) // Instance ID
            ->assertSee('919999999999') // To Number
            ->assertSee('Hello there') // Message
            ->assertSee('My server') // Token name
            ->assertSee($token->token_prefix, escape: false)
            ->assertDontSee($plainText, escape: false) // never the real token, per CLAUDE.md §6/§10
            ->assertSeeInOrder(['success', 'true'])
            ->assertSeeInOrder(['message_id', 'WA-LOG-1']);

        $this->assertSame($token->id, $message->api_token_id);
    }

    public function test_a_failed_call_shows_the_generic_public_error_not_the_internal_one(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        ['token' => $token] = ApiToken::generateFor($instance, 'x');

        $instance->messages()->create([
            'api_token_id' => $token->id,
            'direction' => 'outgoing',
            'to_number' => '919999999999',
            'body' => 'Will fail',
            'status' => 'failed',
            // Internal detail — must never leak into the reconstructed
            // response, since the real API never sent it to the caller.
            'error' => 'Baileys: connection reset by peer at line 42',
        ]);

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSeeInOrder(['error', 'Could not send message'])
            ->assertDontSee('connection reset by peer', escape: false);
    }

    public function test_test_button_messages_never_appear_in_api_logs(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        // No api_token_id — exactly what MessageSender::send() leaves when
        // called from the dashboard's own "send a test message" button.
        Message::factory()->for($instance, 'whatsappSession')->create([
            'api_token_id' => null,
            'body' => 'Sent from the dashboard button',
        ]);

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSee('No API calls logged yet')
            ->assertDontSee('Sent from the dashboard button');
    }

    public function test_incoming_messages_never_appear_in_api_logs(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'incoming',
            'api_token_id' => null,
            'body' => 'A reply from the customer',
        ]);

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertDontSee('A reply from the customer');
    }

    public function test_another_users_api_calls_never_appear(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $otherInstance = WhatsappSession::factory()->for($other)->connected()->create();
        ['token' => $otherToken] = ApiToken::generateFor($otherInstance, 'not mine');

        $otherInstance->messages()->create([
            'api_token_id' => $otherToken->id,
            'direction' => 'outgoing',
            'to_number' => '919999999999',
            'body' => 'Someone elses message',
            'status' => 'sent',
        ]);

        $this->actingAs($me)->get(route('api-logs.index'))
            ->assertOk()
            ->assertDontSee('Someone elses message');
    }

    public function test_logs_span_every_instance_the_user_owns(): void
    {
        $user = User::factory()->create();
        $instanceA = WhatsappSession::factory()->for($user)->connected()->create(['name' => 'Instance A']);
        $instanceB = WhatsappSession::factory()->for($user)->connected()->create(['name' => 'Instance B']);
        ['token' => $tokenA] = ApiToken::generateFor($instanceA, 'Token A');
        ['token' => $tokenB] = ApiToken::generateFor($instanceB, 'Token B');

        $instanceA->messages()->create([
            'api_token_id' => $tokenA->id, 'direction' => 'outgoing',
            'to_number' => '919999999999', 'body' => 'From instance A', 'status' => 'sent',
        ]);
        $instanceB->messages()->create([
            'api_token_id' => $tokenB->id, 'direction' => 'outgoing',
            'to_number' => '919999999999', 'body' => 'From instance B', 'status' => 'sent',
        ]);

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSee('From instance A')
            ->assertSee('From instance B');
    }

    public function test_can_filter_to_a_single_instance(): void
    {
        $user = User::factory()->create();
        $instanceA = WhatsappSession::factory()->for($user)->connected()->create(['name' => 'Instance A']);
        $instanceB = WhatsappSession::factory()->for($user)->connected()->create(['name' => 'Instance B']);
        ['token' => $tokenA] = ApiToken::generateFor($instanceA, 'Token A');
        ['token' => $tokenB] = ApiToken::generateFor($instanceB, 'Token B');

        $instanceA->messages()->create([
            'api_token_id' => $tokenA->id, 'direction' => 'outgoing',
            'to_number' => '919999999999', 'body' => 'From instance A', 'status' => 'sent',
        ]);
        $instanceB->messages()->create([
            'api_token_id' => $tokenB->id, 'direction' => 'outgoing',
            'to_number' => '919999999999', 'body' => 'From instance B', 'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('api-logs.index', ['instance_id' => $instanceA->instance_id]))
            ->assertOk()
            ->assertSee('From instance A')
            ->assertDontSee('From instance B');
    }

    public function test_filtering_by_another_users_instance_id_yields_no_results_not_their_data(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $otherInstance = WhatsappSession::factory()->for($other)->connected()->create();
        ['token' => $otherToken] = ApiToken::generateFor($otherInstance, 'x');
        $otherInstance->messages()->create([
            'api_token_id' => $otherToken->id, 'direction' => 'outgoing',
            'to_number' => '919999999999', 'body' => 'Not visible to me', 'status' => 'sent',
        ]);

        $this->actingAs($me)
            ->get(route('api-logs.index', ['instance_id' => $otherInstance->instance_id]))
            ->assertOk()
            ->assertSee('No API calls logged yet')
            ->assertDontSee('Not visible to me');
    }

    public function test_a_revoked_tokens_logs_still_show_with_its_name(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        ['token' => $token] = ApiToken::generateFor($instance, 'Old integration');
        $token->update(['revoked_at' => now()]);

        $instance->messages()->create([
            'api_token_id' => $token->id,
            'direction' => 'outgoing',
            'to_number' => '919999999999',
            'body' => 'Sent before revocation',
            'status' => 'sent',
        ]);

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSee('Old integration');
    }

    public function test_shows_twenty_api_calls_per_page(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        ['token' => $token] = ApiToken::generateFor($instance, 'x');

        foreach (range(1, 25) as $i) {
            // A factory, so created_at can be set (it isn't mass-assignable).
            Message::factory()->for($instance, 'whatsappSession')->create([
                'api_token_id' => $token->id,
                'direction' => 'outgoing',
                'to_number' => '919999999999',
                'body' => sprintf('Logged call %02d', $i),
                'status' => 'sent',
                'created_at' => now()->subMinutes(100 - $i), // #25 is the newest
            ]);
        }

        $this->actingAs($user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSee('Logged call 25')
            ->assertSee('Logged call 06')
            ->assertDontSee('Logged call 05')
            ->assertSeeInOrder(['Showing', '1', 'to', '20', 'of', '25', 'results']);

        $this->actingAs($user)->get(route('api-logs.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Logged call 05')
            ->assertDontSee('Logged call 06');
    }
}
