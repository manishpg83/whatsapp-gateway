<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MessageStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function getStatus(string $messageId, ?string $token): TestResponse
    {
        $headers = $token ? ['Authorization' => "Bearer {$token}"] : [];

        return $this->withHeaders($headers)->getJson("/api/v1/messages/{$messageId}");
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->getStatus('WA-1', null)
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => 'Missing bearer token']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->getStatus('WA-1', 'not-a-real-token')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => 'Invalid or revoked token']);
    }

    public function test_revoked_token_is_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['token' => $token, 'plainText' => $plainText] = ApiToken::generateFor($instance, 'x');
        Message::factory()->for($instance, 'whatsappSession')->create(['whatsapp_message_id' => 'WA-1']);
        $token->update(['revoked_at' => now()]);

        $this->getStatus('WA-1', $plainText)->assertStatus(401);
    }

    public function test_returns_the_status_of_own_sent_message(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');
        Message::factory()->for($instance, 'whatsappSession')->create([
            'whatsapp_message_id' => 'WA-OWN-1',
            'to_number' => '919999999999',
            'status' => 'sent',
        ]);

        $this->getStatus('WA-OWN-1', $plainText)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'message_id' => 'WA-OWN-1',
                    'instance_id' => $instance->instance_id,
                    'direction' => 'outgoing',
                    'to' => '919999999999',
                    'status' => 'sent',
                ],
            ])
            ->assertJsonStructure(['message' => ['created_at', 'updated_at']]);
    }

    public function test_another_instances_message_is_not_found(): void
    {
        $myInstance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($myInstance, 'x');

        $otherInstance = WhatsappSession::factory()->connected()->create();
        Message::factory()->for($otherInstance, 'whatsappSession')->create(['whatsapp_message_id' => 'WA-OTHER-1']);

        // Same 404 as an unknown id, so nobody can probe which ids exist.
        $this->getStatus('WA-OTHER-1', $plainText)
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'error' => 'Message not found']);
    }

    public function test_same_users_other_instance_message_is_not_found_either(): void
    {
        // A token belongs to ONE instance — even the same owner's other
        // instance is out of its reach.
        $myInstance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($myInstance, 'x');

        $siblingInstance = WhatsappSession::factory()->for($myInstance->user)->connected()->create();
        Message::factory()->for($siblingInstance, 'whatsappSession')->create(['whatsapp_message_id' => 'WA-SIBLING-1']);

        $this->getStatus('WA-SIBLING-1', $plainText)->assertStatus(404);
    }

    public function test_unknown_message_id_is_not_found(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        $this->getStatus('does-not-exist', $plainText)
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'error' => 'Message not found']);
    }

    public function test_incoming_messages_are_not_returned(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');
        Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'incoming',
            'status' => 'received',
            'whatsapp_message_id' => 'WA-IN-1',
        ]);

        $this->getStatus('WA-IN-1', $plainText)->assertStatus(404);
    }

    public function test_never_returns_the_body_or_internal_error(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');
        Message::factory()->for($instance, 'whatsappSession')->create([
            'whatsapp_message_id' => 'WA-PRIVATE-1',
            'body' => 'Private message text',
            'error' => 'Baileys: connection reset by peer at line 42',
        ]);

        $response = $this->getStatus('WA-PRIVATE-1', $plainText)->assertOk();

        $this->assertStringNotContainsString('Private message text', $response->getContent());
        $this->assertStringNotContainsString('connection reset by peer', $response->getContent());
    }

    public function test_status_checks_do_not_use_up_the_send_rate_limit(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');
        Message::factory()->for($instance, 'whatsappSession')->create(['whatsapp_message_id' => 'WA-1']);

        // More than the send limit (30/min), still under the status limit (60/min).
        for ($i = 0; $i < 31; $i++) {
            $this->getStatus('WA-1', $plainText)->assertOk();
        }

        // The send endpoint's own limit is untouched (422 = past throttling,
        // failing only on validation of the empty body).
        $this->withHeaders(['Authorization' => "Bearer {$plainText}"])
            ->postJson('/api/v1/messages/send', [])
            ->assertStatus(422);
    }
}
