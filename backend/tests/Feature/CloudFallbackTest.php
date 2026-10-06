<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CloudFallbackTest extends TestCase
{
    use RefreshDatabase;

    private const WORKER = '127.0.0.1:3001/*';

    private const GRAPH = 'graph.facebook.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function instanceWithFallback(bool $connected = true): WhatsappSession
    {
        $factory = WhatsappSession::factory();

        return ($connected ? $factory->connected() : $factory)->create([
            'fallback_enabled' => true,
            'cloud_phone_number_id' => '106540352242922',
            'cloud_access_token' => 'META-SECRET-TOKEN',
        ]);
    }

    private function send(WhatsappSession $instance, array $extra = []): TestResponse
    {
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        return $this->withHeaders(['Authorization' => "Bearer {$plainText}"])
            ->postJson('/api/v1/messages/send', array_merge([
                'instance_id' => $instance->instance_id,
                'to' => '919999999999',
                'message' => 'Hello there',
            ], $extra));
    }

    public function test_device_failure_falls_back_to_the_cloud_api(): void
    {
        Http::fake([
            self::WORKER => Http::response(['error' => 'boom'], 500),
            self::GRAPH => Http::response(['messages' => [['id' => 'wamid.CLOUD1']]], 200),
        ]);

        $instance = $this->instanceWithFallback();

        $this->send($instance)->assertOk()->assertExactJson([
            'success' => true,
            'message_id' => 'wamid.CLOUD1',
            'sent_via' => 'cloud_api',
            'fallback_status' => 'sent',
        ]);

        $message = Message::firstOrFail();
        $this->assertSame('failed', $message->status);
        $this->assertSame('sent', $message->fallback_status);
        $this->assertSame('wamid.CLOUD1', $message->fallback_message_id);

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://graph.facebook.com/'.config('services.whatsapp_cloud.graph_version').'/106540352242922/messages'
            && $request->hasHeader('Authorization', 'Bearer META-SECRET-TOKEN')
            && $request['to'] === '919999999999'
            && $request['text']['body'] === 'Hello there');
    }

    public function test_cloud_api_failure_is_recorded_as_fallback_failed(): void
    {
        Http::fake([
            self::WORKER => Http::response(['error' => 'boom'], 500),
            self::GRAPH => Http::response(['error' => ['message' => 'Re-engagement message']], 400),
        ]);

        $instance = $this->instanceWithFallback();

        $this->send($instance)->assertStatus(502)->assertJson([
            'success' => false,
            'fallback_status' => 'failed',
        ]);

        $message = Message::firstOrFail();
        $this->assertSame('failed', $message->fallback_status);
        $this->assertStringContainsString('Re-engagement message', $message->fallback_error);
    }

    public function test_disconnected_instance_goes_straight_to_the_cloud_api(): void
    {
        Http::fake([
            self::GRAPH => Http::response(['messages' => [['id' => 'wamid.CLOUD2']]], 200),
        ]);

        $instance = $this->instanceWithFallback(connected: false);

        $this->send($instance)->assertOk()->assertJson(['message_id' => 'wamid.CLOUD2', 'sent_via' => 'cloud_api']);

        // The worker is never asked — only the Cloud API.
        Http::assertSentCount(1);
    }

    public function test_disconnected_instance_without_fallback_is_still_rejected(): void
    {
        Http::fake();

        $instance = WhatsappSession::factory()->create();

        $this->send($instance)->assertStatus(422)->assertJson(['error' => 'Instance is not connected']);
        Http::assertNothingSent();
    }

    public function test_switched_off_fallback_is_not_used(): void
    {
        Http::fake([self::WORKER => Http::response(['error' => 'boom'], 500)]);

        $instance = $this->instanceWithFallback();
        $instance->update(['fallback_enabled' => false]);

        $this->send($instance)->assertStatus(502)->assertJson(['fallback_status' => null]);

        Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_media_messages_never_fall_back(): void
    {
        Http::fake([
            'example.com/*' => Http::response('%PDF-1.4 test', 200, ['Content-Type' => 'application/pdf']),
            self::WORKER => Http::response(['error' => 'boom'], 500),
        ]);

        $instance = $this->instanceWithFallback();

        $this->send($instance, ['type' => 'document', 'media_url' => 'https://example.com/a.pdf'])->assertStatus(502);

        $this->assertNull(Message::firstOrFail()->fallback_status);
        Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_status_endpoint_finds_a_message_by_its_cloud_api_id(): void
    {
        $instance = $this->instanceWithFallback();
        ['plainText' => $plainText] = ApiToken::generateFor($instance, 'x');

        Message::factory()->for($instance, 'whatsappSession')->create([
            'direction' => 'outgoing',
            'status' => 'failed',
            'fallback_status' => 'sent',
            'fallback_message_id' => 'wamid.CLOUD3',
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$plainText}"])
            ->getJson('/api/v1/messages/wamid.CLOUD3')
            ->assertOk()
            ->assertJson(['message' => [
                'message_id' => 'wamid.CLOUD3',
                'sent_via' => 'cloud_api',
                'fallback_status' => 'sent',
            ]]);
    }

    public function test_owner_can_save_keep_and_remove_fallback_settings(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create();

        $this->actingAs($user)->post(route('instances.fallback.update', $instance), [
            'fallback_enabled' => '1',
            'cloud_phone_number_id' => '106540352242922',
            'cloud_access_token' => 'META-SECRET-TOKEN',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $instance->refresh();
        $this->assertTrue($instance->canUseFallback());
        $this->assertSame('META-SECRET-TOKEN', $instance->cloud_access_token);
        // Encrypted at rest — the raw column never holds the plain token.
        $this->assertStringNotContainsString('META-SECRET-TOKEN', DB::table('whatsapp_sessions')->value('cloud_access_token'));

        // A blank token field keeps the saved token.
        $this->actingAs($user)->post(route('instances.fallback.update', $instance), [
            'fallback_enabled' => '0',
            'cloud_phone_number_id' => '106540352242922',
            'cloud_access_token' => '',
        ])->assertSessionHasNoErrors();

        $instance->refresh();
        $this->assertFalse($instance->fallback_enabled);
        $this->assertSame('META-SECRET-TOKEN', $instance->cloud_access_token);

        $this->actingAs($user)->post(route('instances.fallback.update', $instance), ['remove' => '1']);

        $instance->refresh();
        $this->assertNull($instance->cloud_phone_number_id);
        $this->assertNull($instance->cloud_access_token);
    }

    public function test_token_is_required_the_first_time(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create();

        $this->actingAs($user)->post(route('instances.fallback.update', $instance), [
            'fallback_enabled' => '1',
            'cloud_phone_number_id' => '106540352242922',
        ])->assertSessionHasErrors('cloud_access_token');
    }

    public function test_another_user_cannot_change_the_fallback(): void
    {
        $instance = WhatsappSession::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('instances.fallback.update', $instance), [
            'fallback_enabled' => '1',
            'cloud_phone_number_id' => '106540352242922',
            'cloud_access_token' => 'STOLEN',
        ])->assertNotFound();

        $this->assertNull($instance->fresh()->cloud_phone_number_id);
    }

    public function test_instance_page_shows_the_fallback_card_without_the_token(): void
    {
        $user = User::factory()->create();
        $instance = $this->instanceWithFallback();
        $instance->update(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('Cloud API fallback')
            ->assertSee('Fallback is on')
            ->assertDontSee('META-SECRET-TOKEN');
    }
}
