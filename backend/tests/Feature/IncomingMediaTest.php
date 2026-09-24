<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('whatsapp_media');
        config(['worker.secret' => 'test-internal-secret-123456']);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function postEvent(array $payload)
    {
        return $this->withHeaders(['X-Internal-Secret' => config('worker.secret')])
            ->postJson(route('internal.worker.events'), $payload);
    }

    private function receivedPayload(WhatsappSession $instance, array $overrides = []): array
    {
        return array_merge([
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => '919876543210',
            'type' => 'image',
            'message' => 'Here is the photo',
            'whatsapp_message_id' => '3EB0ABC',
            'timestamp' => '2026-09-24T10:15:03.000Z',
            'media' => [
                'status' => 'stored',
                'path' => "{$instance->instance_id}/3EB0ABC.jpg",
                'mime_type' => 'image/jpeg',
                'file_name' => null,
                'size' => 11,
            ],
        ], $overrides);
    }

    private function storedImage(WhatsappSession $instance, array $attributes = []): Message
    {
        Storage::disk('whatsapp_media')->put("{$instance->instance_id}/IMG1.jpg", 'fake-jpeg');

        return $instance->messages()->create(array_merge([
            'direction' => 'incoming',
            'type' => 'image',
            'from_number' => '919876543210',
            'body' => 'A caption',
            'status' => 'received',
            'whatsapp_message_id' => 'IMG1',
            'media_status' => 'stored',
            'media_path' => "{$instance->instance_id}/IMG1.jpg",
            'media_mime_type' => 'image/jpeg',
            'media_size' => 9,
        ], $attributes));
    }

    // --- Worker callback ----------------------------------------------

    public function test_an_incoming_image_is_stored_with_its_media_details(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postEvent($this->receivedPayload($instance))->assertNoContent();

        $message = Message::sole();
        $this->assertSame('image', $message->type);
        $this->assertSame('Here is the photo', $message->body);
        $this->assertSame('stored', $message->media_status);
        $this->assertSame("{$instance->instance_id}/3EB0ABC.jpg", $message->media_path);
        $this->assertSame('image/jpeg', $message->media_mime_type);
        $this->assertSame(11, $message->media_size);
    }

    public function test_a_voice_note_without_caption_is_stored_with_an_empty_body(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postEvent($this->receivedPayload($instance, [
            'type' => 'voice',
            'message' => '',
            'media' => ['status' => 'stored', 'path' => "{$instance->instance_id}/V1.ogg", 'mime_type' => 'audio/ogg', 'file_name' => null, 'size' => 5],
        ]))->assertNoContent();

        $message = Message::sole();
        $this->assertSame('voice', $message->type);
        $this->assertSame('', $message->body);
    }

    public function test_old_style_text_only_callbacks_still_work(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postEvent([
            'event' => 'message.received',
            'instance_id' => $instance->instance_id,
            'from' => '919876543210',
            'message' => 'Plain hello',
            'whatsapp_message_id' => 'T1',
            'timestamp' => '2026-09-24T10:15:03.000Z',
        ])->assertNoContent();

        $message = Message::sole();
        $this->assertSame('text', $message->type);
        $this->assertNull($message->media_status);
    }

    public function test_a_too_large_file_is_recorded_without_a_path(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postEvent($this->receivedPayload($instance, [
            'type' => 'video',
            'media' => ['status' => 'too_large', 'path' => null, 'mime_type' => 'video/mp4', 'file_name' => null, 'size' => 500000000],
        ]))->assertNoContent();

        $message = Message::sole();
        $this->assertSame('too_large', $message->media_status);
        $this->assertNull($message->media_path);
    }

    public function test_a_path_outside_the_instances_own_folder_is_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        $other = WhatsappSession::factory()->connected()->create();

        foreach ([
            "{$other->instance_id}/3EB0ABC.jpg",      // another instance's folder
            "{$instance->instance_id}/../secret.jpg", // path traversal
            '../../.env',
            "{$instance->instance_id}/sub/dir.jpg",
        ] as $i => $badPath) {
            $this->postEvent($this->receivedPayload($instance, [
                'whatsapp_message_id' => "BAD{$i}",
                'media' => ['status' => 'stored', 'path' => $badPath, 'mime_type' => 'image/jpeg', 'file_name' => null, 'size' => 1],
            ]))->assertNoContent();

            $message = Message::where('whatsapp_message_id', "BAD{$i}")->sole();
            $this->assertNull($message->media_path, "Path should have been rejected: {$badPath}");
            $this->assertSame('failed', $message->media_status);
        }
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->postEvent($this->receivedPayload($instance, ['type' => 'hologram']))->assertStatus(422);
    }

    public function test_the_customer_webhook_gets_the_type_and_a_signed_24h_media_link(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $this->postEvent($this->receivedPayload($instance))->assertNoContent();

        Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
            $media = $job->payload['media'];

            return $job->payload['type'] === 'image'
                && $job->payload['message'] === 'Here is the photo'
                && $media['status'] === 'stored'
                && $media['mime_type'] === 'image/jpeg'
                && str_contains($media['url'], '/media/')
                && str_contains($media['url'], 'signature=')
                && str_contains($media['url'], 'expires=');
        });
    }

    public function test_the_webhook_has_no_media_url_when_the_file_was_not_stored(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'a-fixed-webhook-secret',
        ]);

        $this->postEvent($this->receivedPayload($instance, [
            'media' => ['status' => 'failed', 'path' => null, 'mime_type' => 'image/jpeg', 'file_name' => null, 'size' => null],
        ]));

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->payload['media']['url'] === null);
    }

    // --- Serving files in the dashboard --------------------------------

    public function test_owner_can_view_their_image_inline(): void
    {
        $user = User::factory()->create();
        $message = $this->storedImage(WhatsappSession::factory()->for($user)->connected()->create());

        $response = $this->actingAs($user)->get(route('messages.media', $message));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', 'sandbox');
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('fake-jpeg', $response->streamedContent());
    }

    public function test_download_flag_forces_an_attachment(): void
    {
        $user = User::factory()->create();
        $message = $this->storedImage(WhatsappSession::factory()->for($user)->connected()->create());

        $response = $this->actingAs($user)->get(route('messages.media', $message).'?download=1')->assertOk();

        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_documents_are_always_downloaded_never_rendered(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        Storage::disk('whatsapp_media')->put("{$instance->instance_id}/DOC1.html", '<script>alert(1)</script>');
        $message = $instance->messages()->create([
            'direction' => 'incoming', 'type' => 'document', 'from_number' => '919876543210', 'body' => '',
            'status' => 'received', 'whatsapp_message_id' => 'DOC1',
            'media_status' => 'stored', 'media_path' => "{$instance->instance_id}/DOC1.html",
            'media_mime_type' => 'text/html', 'media_file_name' => 'page.html',
        ]);

        $response = $this->actingAs($user)->get(route('messages.media', $message))->assertOk();

        $response->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('page.html', $response->headers->get('Content-Disposition'));
    }

    public function test_another_user_cannot_view_the_file(): void
    {
        $message = $this->storedImage(WhatsappSession::factory()->connected()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('messages.media', $message))
            ->assertNotFound();
    }

    public function test_guest_cannot_view_the_file_without_a_signed_link(): void
    {
        $message = $this->storedImage(WhatsappSession::factory()->connected()->create());

        $this->get(route('messages.media', $message))->assertRedirect(route('login'));
        $this->get(route('media.signed', $message))->assertForbidden(); // unsigned
    }

    public function test_signed_link_works_without_login_until_it_expires(): void
    {
        $message = $this->storedImage(WhatsappSession::factory()->connected()->create());
        $url = $message->temporaryMediaUrl();

        $this->get($url)->assertOk();

        $this->travel(25)->hours();
        $this->get($url)->assertForbidden();
    }

    public function test_a_missing_file_gives_404(): void
    {
        $user = User::factory()->create();
        $message = $this->storedImage(WhatsappSession::factory()->for($user)->connected()->create());
        Storage::disk('whatsapp_media')->delete($message->media_path);

        $this->actingAs($user)->get(route('messages.media', $message))->assertNotFound();
    }

    // --- Pages -----------------------------------------------------------

    public function test_messages_page_shows_the_image_thumbnail_and_caption(): void
    {
        $user = User::factory()->create();
        $message = $this->storedImage(WhatsappSession::factory()->for($user)->connected()->create());

        $this->actingAs($user)->get(route('messages.index'))
            ->assertOk()
            ->assertSee(route('messages.media', $message), escape: false)
            ->assertSee('A caption');
    }

    public function test_messages_page_shows_a_note_for_a_too_large_file(): void
    {
        $user = User::factory()->create();
        WhatsappSession::factory()->for($user)->connected()->create()->messages()->create([
            'direction' => 'incoming', 'type' => 'video', 'from_number' => '919876543210', 'body' => '',
            'status' => 'received', 'whatsapp_message_id' => 'BIG1',
            'media_status' => 'too_large', 'media_mime_type' => 'video/mp4', 'media_size' => 500000000,
        ]);

        $this->actingAs($user)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('File too large to download');
    }

    public function test_messages_page_can_filter_by_type(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        $this->storedImage($instance, ['body' => 'Photo caption']);
        Message::factory()->for($instance, 'whatsappSession')->create(['body' => 'Just some text']);

        $this->actingAs($user)->get(route('messages.index', ['type' => 'image']))
            ->assertOk()
            ->assertSee('Photo caption')
            ->assertDontSee('Just some text');
    }

    // --- Deletion --------------------------------------------------------

    public function test_deleting_an_account_deletes_its_media_files(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123']);
        $instance = WhatsappSession::factory()->for($user)->create();
        $this->storedImage($instance);
        $otherInstance = WhatsappSession::factory()->create();
        Storage::disk('whatsapp_media')->put("{$otherInstance->instance_id}/KEEP.jpg", 'x');

        $this->actingAs($user)->delete(route('account.destroy'), ['current_password' => 'secret-pass-123']);

        Storage::disk('whatsapp_media')->assertMissing("{$instance->instance_id}/IMG1.jpg");
        Storage::disk('whatsapp_media')->assertExists("{$otherInstance->instance_id}/KEEP.jpg");
    }
}
