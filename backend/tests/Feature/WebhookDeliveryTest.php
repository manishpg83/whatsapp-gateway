<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WhatsappSession;
use App\Rules\PublicWebhookUrl;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
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

    private function instanceWithWebhook(?User $user = null, string $url = 'https://example.test/webhook'): WhatsappSession
    {
        return WhatsappSession::factory()->for($user ?? User::factory())->connected()->create([
            'webhook_url' => $url,
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);
    }

    /**
     * Laravel only skips CSRF checks while the environment is "testing",
     * so switching to "production" for these tests has to switch it off
     * explicitly — the CSRF check itself isn't what's being tested here.
     */
    private function actAsProduction(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    // --- Real (queued) deliveries -------------------------------------

    public function test_dispatch_creates_a_queued_delivery_row_and_passes_its_id_to_the_job(): void
    {
        Queue::fake();
        $instance = $this->instanceWithWebhook();

        app(WebhookDispatcher::class)->dispatch($instance, ['event' => 'message.received', 'message' => 'Hi']);

        $delivery = $instance->webhookDeliveries()->sole();
        $this->assertSame('message.received', $delivery->event);
        $this->assertSame('https://example.test/webhook', $delivery->url);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertSame('Queued', $delivery->statusLabel());

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->webhookDeliveryId === $delivery->id);
    }

    public function test_dispatch_does_nothing_without_a_webhook_url(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        app(WebhookDispatcher::class)->dispatch($instance, ['event' => 'message.received']);

        $this->assertSame(0, WebhookDelivery::count());
        Queue::assertNothingPushed();
    }

    public function test_a_successful_attempt_marks_the_delivery_delivered(): void
    {
        Http::fake(['https://example.test/webhook' => Http::response('ok', 200)]);
        $instance = $this->instanceWithWebhook();
        $delivery = $instance->webhookDeliveries()->create(['event' => 'message.received', 'url' => $instance->webhook_url]);

        (new DeliverWebhook($instance->id, ['event' => 'message.received'], $delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame('success', $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNull($delivery->error);
    }

    public function test_a_failed_attempt_records_the_http_status_and_stays_pending_for_retry(): void
    {
        Http::fake(['https://example.test/webhook' => Http::response('<html>secret stack trace</html>', 500)]);
        $instance = $this->instanceWithWebhook();
        $delivery = $instance->webhookDeliveries()->create(['event' => 'message.received', 'url' => $instance->webhook_url]);

        try {
            (new DeliverWebhook($instance->id, ['event' => 'message.received'], $delivery->id))->handle();
            $this->fail('Expected the job to throw so the queue retries it.');
        } catch (\Illuminate\Http\Client\RequestException) {
        }

        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('Retrying', $delivery->statusLabel());
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame('Your server replied HTTP 500', $delivery->error);
        // Never the receiver's response body.
        $this->assertStringNotContainsString('secret stack trace', $delivery->error);
    }

    public function test_the_delivery_is_marked_failed_once_retries_are_exhausted(): void
    {
        $instance = $this->instanceWithWebhook();
        $delivery = $instance->webhookDeliveries()->create(['event' => 'message.received', 'url' => $instance->webhook_url]);

        (new DeliverWebhook($instance->id, [], $delivery->id))->failed(new ConnectionException('Connection refused'));

        $this->assertSame('failed', $delivery->refresh()->status);
    }

    public function test_a_redirect_is_not_followed_and_counts_as_a_failure(): void
    {
        Http::fake(['https://example.test/webhook' => Http::response('', 302, ['Location' => 'http://127.0.0.1:3001/sessions'])]);
        $instance = $this->instanceWithWebhook();
        $delivery = $instance->webhookDeliveries()->create(['event' => 'message.received', 'url' => $instance->webhook_url]);

        try {
            (new DeliverWebhook($instance->id, ['event' => 'message.received'], $delivery->id))->handle();
            $this->fail('Expected a redirect to count as a failure.');
        } catch (\RuntimeException) {
        }

        Http::assertSentCount(1); // the redirect target was never requested
        $delivery->refresh();
        $this->assertSame(302, $delivery->response_status);
        $this->assertStringContainsString('redirect', $delivery->error);
    }

    // --- "Send test webhook" button ------------------------------------

    public function test_owner_can_send_a_test_webhook_that_succeeds(): void
    {
        Http::fake(['https://example.test/webhook' => Http::response('', 200)]);
        $user = User::factory()->create();
        $instance = $this->instanceWithWebhook($user);

        $this->actingAs($user)->post(route('instances.webhook.test', $instance))
            ->assertRedirect(route('instances.show', $instance))
            ->assertSessionHas('status', 'Test webhook delivered — your server replied HTTP 200.');

        Http::assertSent(function ($request) use ($instance) {
            $expectedSignature = 'sha256='.hash_hmac('sha256', $request->body(), 'a-fixed-webhook-secret');
            $payload = json_decode($request->body(), true);

            return $request->header('X-Webhook-Signature')[0] === $expectedSignature
                && $payload['event'] === 'webhook.test'
                && $payload['instance_id'] === $instance->instance_id;
        });

        $delivery = $instance->webhookDeliveries()->sole();
        $this->assertSame('webhook.test', $delivery->event);
        $this->assertSame('success', $delivery->status);
    }

    public function test_a_failing_test_webhook_shows_the_error_and_is_logged_as_failed(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });
        $user = User::factory()->create();
        $instance = $this->instanceWithWebhook($user);

        $this->actingAs($user)->post(route('instances.webhook.test', $instance))
            ->assertRedirect(route('instances.show', $instance))
            ->assertSessionHas('error', 'Test webhook failed: Connection refused');

        $delivery = $instance->webhookDeliveries()->sole();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
    }

    public function test_test_webhook_needs_a_saved_url(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.webhook.test', $instance))
            ->assertSessionHas('error', 'Save a webhook URL first.');

        Http::assertNothingSent();
    }

    public function test_cannot_send_a_test_webhook_for_another_users_instance(): void
    {
        Http::fake();
        $otherInstance = $this->instanceWithWebhook();

        $this->actingAs(User::factory()->create())
            ->post(route('instances.webhook.test', $otherInstance))
            ->assertNotFound();

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_guest_cannot_send_a_test_webhook(): void
    {
        $instance = $this->instanceWithWebhook();

        $this->post(route('instances.webhook.test', $instance))->assertRedirect(route('login'));
    }

    public function test_test_webhook_is_rate_limited_to_five_per_minute(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $user = User::factory()->create();
        $instance = $this->instanceWithWebhook($user);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post(route('instances.webhook.test', $instance))->assertRedirect();
        }

        $this->actingAs($user)->post(route('instances.webhook.test', $instance))->assertStatus(429);
    }

    // --- Instance page -------------------------------------------------

    public function test_instance_page_shows_recent_deliveries_and_the_test_button(): void
    {
        $user = User::factory()->create();
        $instance = $this->instanceWithWebhook($user);
        $instance->webhookDeliveries()->create([
            'event' => 'message.received', 'url' => $instance->webhook_url,
            'status' => 'failed', 'attempts' => 5, 'response_status' => 503, 'error' => 'Your server replied HTTP 503',
        ]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('Send test webhook')
            ->assertSee('Recent deliveries')
            ->assertSee('message.received')
            ->assertSee('Your server replied HTTP 503');
    }

    public function test_instance_page_never_shows_another_instances_deliveries(): void
    {
        $user = User::factory()->create();
        $instance = $this->instanceWithWebhook($user);
        $otherInstance = $this->instanceWithWebhook();
        $otherInstance->webhookDeliveries()->create([
            'event' => 'message.received', 'url' => $otherInstance->webhook_url, 'error' => 'Other owner error',
        ]);

        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee('No webhooks sent yet')
            ->assertDontSee('Other owner error');
    }

    // --- Private / local URL blocking (production only) ----------------

    public function test_private_and_local_urls_are_allowed_outside_production(): void
    {
        $this->assertTrue(PublicWebhookUrl::isAllowed('http://127.0.0.1:9000/hook'));
    }

    public function test_private_and_local_urls_are_blocked_in_production(): void
    {
        $this->actAsProduction();

        foreach ([
            'http://127.0.0.1:3001/sessions',
            'http://localhost/hook',
            'http://10.0.0.5/hook',
            'http://192.168.1.10/hook',
            'http://172.16.0.1/hook',
            'http://169.254.169.254/latest/meta-data',
            'http://100.64.0.1/hook',
            'http://[::1]/hook',
            'http://0.0.0.0/hook',
        ] as $url) {
            $this->assertFalse(PublicWebhookUrl::isAllowed($url), "{$url} should be blocked");
        }

        $this->assertTrue(PublicWebhookUrl::isAllowed('https://93.184.216.34/hook'));
    }

    public function test_saving_a_private_webhook_url_is_rejected_in_production(): void
    {
        $this->actAsProduction();
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.webhook.update', $instance), [
            'webhook_url' => 'http://127.0.0.1:3001/sessions',
        ])->assertSessionHasErrors('webhook_url');

        $this->assertNull($instance->refresh()->webhook_url);
    }

    public function test_a_saved_url_that_became_private_is_not_called_in_production(): void
    {
        Http::fake();
        $this->actAsProduction();
        $user = User::factory()->create();
        // e.g. saved before this check existed, or its DNS changed since.
        $instance = $this->instanceWithWebhook($user, 'http://127.0.0.1:3001/sessions');

        $this->actingAs($user)->post(route('instances.webhook.test', $instance))
            ->assertSessionHas('error', 'Test webhook failed: Webhook URL points to localhost or a private network');

        Http::assertNothingSent();
    }
}
