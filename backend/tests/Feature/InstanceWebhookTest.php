<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstanceWebhookTest extends TestCase
{
    use RefreshDatabase;

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_owner_can_set_a_webhook_url_and_gets_a_secret(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.webhook.update', $instance), [
            'webhook_url' => 'https://example.test/webhook',
        ])->assertRedirect(route('instances.show', $instance));

        $instance->refresh();
        $this->assertSame('https://example.test/webhook', $instance->webhook_url);
        $this->assertNotNull($instance->webhook_secret);
    }

    public function test_updating_the_url_keeps_the_same_secret(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create([
            'webhook_url' => 'https://example.test/old',
            'webhook_secret' => 'original-secret',
        ]);

        $this->actingAs($user)->post(route('instances.webhook.update', $instance), [
            'webhook_url' => 'https://example.test/new',
        ]);

        $instance->refresh();
        $this->assertSame('https://example.test/new', $instance->webhook_url);
        $this->assertSame('original-secret', $instance->webhook_secret);
    }

    public function test_owner_can_clear_the_webhook(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-secret',
        ]);

        $this->actingAs($user)->post(route('instances.webhook.update', $instance), [
            'webhook_url' => '',
        ]);

        $instance->refresh();
        $this->assertNull($instance->webhook_url);
        $this->assertNull($instance->webhook_secret);
    }

    public function test_invalid_url_is_rejected(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.webhook.update', $instance), [
            'webhook_url' => 'not-a-url',
        ])->assertSessionHasErrors('webhook_url');

        $this->assertNull($instance->fresh()->webhook_url);
    }

    public function test_user_cannot_set_a_webhook_on_another_users_instance(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->connected()->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('instances.webhook.update', $instance), [
            'webhook_url' => 'https://example.test/webhook',
        ])->assertNotFound();

        $this->assertNull($instance->fresh()->webhook_url);
    }
}
