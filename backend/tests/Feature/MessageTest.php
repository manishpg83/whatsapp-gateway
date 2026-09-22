<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function send(?string $token, array $payload): TestResponse
    {
        $headers = $token ? ['Authorization' => "Bearer {$token}"] : [];

        return $this->withHeaders($headers)->postJson('/api/v1/messages/send', $payload);
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->send(null, ['instance_id' => 'x', 'to' => '919999999999', 'message' => 'hi'])
            ->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->send('not-a-real-token', ['instance_id' => 'x', 'to' => '919999999999', 'message' => 'hi'])
            ->assertUnauthorized();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['token' => $token, 'plainText' => $plainText] = ApiToken::generateFor($instance, 'x');
        $token->update(['revoked_at' => now()]);

        $this->send($plainText, ['instance_id' => $instance->instance_id, 'to' => '919999999999', 'message' => 'hi'])
            ->assertUnauthorized();
    }

    public function test_instance_id_mismatch_is_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        $otherInstance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        // Token belongs to $instance, but the body claims $otherInstance.
        $this->send($plainText, ['instance_id' => $otherInstance->instance_id, 'to' => '919999999999', 'message' => 'hi'])
            ->assertStatus(422);

        $this->assertSame(0, Message::count());
    }

    public function test_message_is_rejected_when_the_instance_is_not_connected(): void
    {
        $instance = WhatsappSession::factory()->create(['status' => 'qr_pending']);
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        $this->send($plainText, ['instance_id' => $instance->instance_id, 'to' => '919999999999', 'message' => 'hi'])
            ->assertStatus(422);

        $this->assertSame(0, Message::count());
    }

    public function test_invalid_to_format_is_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        $this->send($plainText, ['instance_id' => $instance->instance_id, 'to' => 'not-a-number', 'message' => 'hi'])
            ->assertStatus(422);
    }

    public function test_successful_send_creates_and_updates_the_message_record(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'WA-MESSAGE-123'], 200)]);

        $instance = WhatsappSession::factory()->connected()->create();
        ['token' => $token, 'plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        $response = $this->send($plainText, [
            'instance_id' => $instance->instance_id,
            'to' => '919999999999',
            'message' => 'Hello there',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'message_id' => 'WA-MESSAGE-123']);

        $message = Message::where('whatsapp_session_id', $instance->id)->firstOrFail();
        $this->assertSame('sent', $message->status);
        $this->assertSame('WA-MESSAGE-123', $message->whatsapp_message_id);
        $this->assertSame('919999999999', $message->to_number);
        $this->assertSame('Hello there', $message->body);

        $this->assertNotNull($token->fresh()->last_used_at);

        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:3001/sessions/{$instance->instance_id}/messages"
            && $request['to'] === '919999999999');
    }

    public function test_worker_failure_marks_the_message_failed(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        $response = $this->send($plainText, [
            'instance_id' => $instance->instance_id,
            'to' => '919999999999',
            'message' => 'Hello there',
        ]);

        $response->assertStatus(502)->assertJson(['success' => false]);

        $message = Message::where('whatsapp_session_id', $instance->id)->firstOrFail();
        $this->assertSame('failed', $message->status);
        $this->assertNotNull($message->error);
    }

    public function test_send_endpoint_carries_rate_limit_headers(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'x'], 200)]);

        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        $this->send($plainText, ['instance_id' => $instance->instance_id, 'to' => '919999999999', 'message' => 'hi'])
            ->assertHeader('X-RateLimit-Limit', 30);
    }
}
