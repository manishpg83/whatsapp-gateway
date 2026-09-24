<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RejectedApiRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappSession $instance;

    private ApiToken $token;

    private string $plainText;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->instance = WhatsappSession::factory()->for($this->user)->connected()->create(['name' => 'Shop phone']);
        ['token' => $this->token, 'plainText' => $this->plainText] = ApiToken::generateFor($this->instance, 'My server');
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function send(array $payload, ?string $token = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.($token ?? $this->plainText)])
            ->postJson('/api/v1/messages/send', $payload);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'instance_id' => $this->instance->instance_id,
            'to' => '919999999999',
            'message' => 'Secret message text',
        ], $overrides);
    }

    public function test_a_validation_error_is_logged_with_the_callers_error_text(): void
    {
        $this->send($this->validPayload(['to' => 'not-a-number']))->assertStatus(422);

        $log = ApiRequestLog::sole();
        $this->assertSame($this->instance->id, $log->whatsapp_session_id);
        $this->assertSame($this->token->id, $log->api_token_id);
        $this->assertSame(422, $log->status_code);
        $this->assertSame('POST', $log->method);
        $this->assertSame('/api/v1/messages/send', $log->path);
        $this->assertStringStartsWith('to: ', $log->error);
        $this->assertSame('not-a-number', $log->to_number);
    }

    public function test_instance_not_connected_is_logged(): void
    {
        $this->instance->update(['status' => 'disconnected']);

        $this->send($this->validPayload())->assertStatus(422);

        $this->assertSame('Instance is not connected', ApiRequestLog::sole()->error);
    }

    public function test_instance_id_mismatch_is_logged(): void
    {
        $this->send($this->validPayload(['instance_id' => '11111111-1111-1111-1111-111111111111']))->assertStatus(422);

        $this->assertSame('instance_id does not match this token', ApiRequestLog::sole()->error);
    }

    public function test_a_revoked_token_is_logged_for_its_owner(): void
    {
        $this->token->update(['revoked_at' => now()]);

        $this->send($this->validPayload())->assertUnauthorized();

        $log = ApiRequestLog::sole();
        $this->assertSame(401, $log->status_code);
        $this->assertSame($this->token->id, $log->api_token_id);
        $this->assertSame('Invalid or revoked token', $log->error);
    }

    public function test_an_unknown_token_is_not_logged(): void
    {
        $this->send($this->validPayload(), 'totally-made-up-token')->assertUnauthorized();

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_the_rate_limit_is_logged(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'x'], 200)]);

        for ($i = 0; $i < 30; $i++) {
            $this->send($this->validPayload())->assertOk();
        }
        $this->send($this->validPayload())->assertStatus(429);

        $this->assertSame(429, ApiRequestLog::sole()->status_code);
    }

    public function test_successful_and_worker_failed_sends_are_not_logged_here(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message_id' => 'WA-1'], 200)
            ->push([], 502)]);

        $this->send($this->validPayload())->assertOk();
        $this->send($this->validPayload())->assertStatus(502); // already on "API calls" as a failed message

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_the_message_text_and_token_are_never_stored(): void
    {
        $this->instance->update(['status' => 'disconnected']);
        $this->send($this->validPayload())->assertStatus(422);

        $row = json_encode(ApiRequestLog::sole()->getAttributes());
        $this->assertStringNotContainsString('Secret message text', $row);
        $this->assertStringNotContainsString($this->plainText, $row);
    }

    // --- The page -------------------------------------------------------------

    public function test_rejected_tab_lists_the_owners_rejected_calls(): void
    {
        $this->instance->update(['status' => 'disconnected']);
        $this->send($this->validPayload())->assertStatus(422);

        $this->actingAs($this->user)->get(route('api-logs.index', ['tab' => 'rejected']))
            ->assertOk()
            ->assertSee('Instance is not connected')
            ->assertSee('Shop phone')
            ->assertSee('My server')
            ->assertSee('919999999999');
    }

    public function test_the_tab_shows_a_count_badge(): void
    {
        $this->instance->update(['status' => 'disconnected']);
        $this->send($this->validPayload())->assertStatus(422);
        $this->send($this->validPayload())->assertStatus(422);

        $this->actingAs($this->user)->get(route('api-logs.index'))
            ->assertOk()
            ->assertSeeInOrder(['Rejected requests', '2']);
    }

    public function test_another_users_rejected_calls_are_never_shown(): void
    {
        $this->instance->update(['status' => 'disconnected']);
        $this->send($this->validPayload())->assertStatus(422);

        $this->actingAs(User::factory()->create())
            ->get(route('api-logs.index', ['tab' => 'rejected']))
            ->assertOk()
            ->assertSee('No rejected requests')
            ->assertDontSee('Instance is not connected');
    }

    public function test_rejected_tab_filters_by_instance(): void
    {
        $other = WhatsappSession::factory()->for($this->user)->create(['status' => 'disconnected', 'name' => 'Other phone']);
        ['plainText' => $otherToken] = ApiToken::generateFor($other, 'x');
        $this->instance->update(['status' => 'disconnected']);

        $this->send($this->validPayload())->assertStatus(422);
        $this->send($this->validPayload(['instance_id' => $other->instance_id]), $otherToken)->assertStatus(422);

        $this->actingAs($this->user)->get(route('api-logs.index', ['tab' => 'rejected', 'instance_id' => $other->instance_id]))
            ->assertOk()
            ->assertSee('Other phone')
            ->assertDontSee('Shop phone</td>', escape: false);
    }
}
