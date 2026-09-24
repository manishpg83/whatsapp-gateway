<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SendMediaTest extends TestCase
{
    use RefreshDatabase;

    // Tiny files with real "magic bytes", so PHP's file-type detection
    // (finfo) recognises them exactly as it would a real upload.
    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private const OGG = "OggS\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x13OpusHead\x01\x01\x38\x01\x80\xBB\x00\x00\x00\x00\x00";

    private WhatsappSession $instance;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('whatsapp_media');

        $this->instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $this->token] = ApiToken::generateFor($this->instance, 'x');
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    /**
     * Fakes both the file server behind media_url and the worker.
     */
    private function fakeHttp(string $fileBody = self::JPEG, int $fileStatus = 200, int $workerStatus = 200): void
    {
        Http::fake([
            'files.example.test/*' => Http::response($fileBody, $fileStatus),
            '127.0.0.1:3001/*' => Http::response(['message_id' => 'WA-MEDIA-1'], $workerStatus),
        ]);
    }

    private function send(array $payload): TestResponse
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/messages/send', array_merge([
                'instance_id' => $this->instance->instance_id,
                'to' => '919999999999',
            ], $payload));
    }

    private function workerRequest(): ?Request
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), '127.0.0.1:3001'))->first()[0] ?? null;
    }

    // --- Happy paths ---------------------------------------------------------

    public function test_sends_an_image_with_a_caption(): void
    {
        $this->fakeHttp(self::JPEG);

        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/photo.jpg', 'message' => 'Look at this'])
            ->assertOk()
            ->assertJson(['success' => true, 'message_id' => 'WA-MEDIA-1']);

        $message = Message::sole();
        $this->assertSame('image', $message->type);
        $this->assertSame('Look at this', $message->body);
        $this->assertSame('sent', $message->status);
        $this->assertSame('stored', $message->media_status);
        $this->assertSame('image/jpeg', $message->media_mime_type);
        $this->assertSame(strlen(self::JPEG), $message->media_size);
        $this->assertMatchesRegularExpression('#^'.$this->instance->instance_id.'/out-[0-9a-f-]{36}\.jpg$#', $message->media_path);
        Storage::disk('whatsapp_media')->assertExists($message->media_path);

        $worker = $this->workerRequest();
        $this->assertSame('image', $worker['type']);
        $this->assertSame('Look at this', $worker['message']);
        $this->assertSame($message->media_path, $worker['media']['path']);
        $this->assertSame('image/jpeg', $worker['media']['mime_type']);
    }

    public function test_sends_a_document_with_a_custom_file_name(): void
    {
        $this->fakeHttp(self::PDF);

        $this->send([
            'type' => 'document',
            'media_url' => 'https://files.example.test/download?id=42',
            'file_name' => 'Invoice-42.pdf',
            'message' => 'Your invoice',
        ])->assertOk();

        $message = Message::sole();
        $this->assertSame('document', $message->type);
        $this->assertSame('application/pdf', $message->media_mime_type);
        $this->assertSame('Invoice-42.pdf', $message->media_file_name);
        $this->assertStringEndsWith('.pdf', $message->media_path);
        $this->assertSame('Invoice-42.pdf', $this->workerRequest()['media']['file_name']);
    }

    public function test_a_documents_name_defaults_to_the_url_file_name(): void
    {
        $this->fakeHttp(self::PDF);

        $this->send(['type' => 'document', 'media_url' => 'https://files.example.test/files/Price%20List.pdf'])->assertOk();

        $this->assertSame('Price List.pdf', Message::sole()->media_file_name);
    }

    public function test_sends_a_voice_note_and_ignores_any_caption(): void
    {
        $this->fakeHttp(self::OGG);

        $this->send(['type' => 'voice', 'media_url' => 'https://files.example.test/note.ogg', 'message' => 'ignored'])
            ->assertOk();

        $message = Message::sole();
        $this->assertSame('voice', $message->type);
        $this->assertSame('', $message->body);
        $this->assertStringEndsWith('.ogg', $message->media_path);
        $this->assertSame('', $this->workerRequest()['message']);
    }

    public function test_plain_text_still_works_without_a_type(): void
    {
        $this->fakeHttp();

        $this->send(['message' => 'Just text'])->assertOk();

        $message = Message::sole();
        $this->assertSame('text', $message->type);
        $this->assertNull($message->media_path);
        $this->assertArrayNotHasKey('media', $this->workerRequest()->data());
    }

    // --- Validation ----------------------------------------------------------

    public function test_media_types_need_a_media_url(): void
    {
        $this->fakeHttp();

        $this->send(['type' => 'image', 'message' => 'caption only'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['media_url' => 'Send either media_url or a media file when type is not text.']);
    }

    public function test_text_messages_cannot_have_a_media_url(): void
    {
        $this->fakeHttp();

        $this->send(['message' => 'hi', 'media_url' => 'https://files.example.test/photo.jpg'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('media_url');
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->fakeHttp();

        $this->send(['type' => 'sticker', 'media_url' => 'https://files.example.test/s.webp'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    // --- media_url problems ---------------------------------------------------

    public function test_the_wrong_file_type_is_rejected_and_nothing_is_sent(): void
    {
        $this->fakeHttp(self::PDF); // a PDF...

        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/photo.jpg']) // ...claiming to be an image
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => "The file at media_url is application/pdf, which can't be sent as image."]);

        $this->assertSame(0, Message::count());
        $this->assertNull($this->workerRequest());
        $this->assertSame([], Storage::disk('whatsapp_media')->allFiles());
    }

    public function test_a_file_over_the_size_limit_is_rejected(): void
    {
        $this->fakeHttp(self::JPEG.str_repeat("\0", 5 * 1024 * 1024));

        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/huge.jpg'])
            ->assertStatus(422)
            ->assertJson(['error' => 'The file at media_url is too large (max 5 MB for this type).']);

        $this->assertNull($this->workerRequest());
        $this->assertSame([], Storage::disk('whatsapp_media')->allFiles());
    }

    public function test_a_media_url_that_returns_an_error_is_rejected(): void
    {
        $this->fakeHttp('Not found', 404);

        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/missing.jpg'])
            ->assertStatus(422)
            ->assertJson(['error' => 'Could not download media_url (it returned HTTP 404).']);
    }

    public function test_an_empty_file_is_rejected(): void
    {
        $this->fakeHttp('');

        $this->send(['type' => 'document', 'media_url' => 'https://files.example.test/empty.pdf'])
            ->assertStatus(422)
            ->assertJson(['error' => 'The file at media_url is empty.']);
    }

    public function test_private_addresses_are_refused_in_production(): void
    {
        $this->fakeHttp();
        $this->app['env'] = 'production';

        $this->send(['type' => 'image', 'media_url' => 'http://127.0.0.1:3001/sessions'])
            ->assertStatus(422)
            ->assertJson(['error' => 'media_url must be a public http(s) address.']);

        Http::assertNothingSent();
    }

    public function test_non_http_urls_are_refused(): void
    {
        $this->fakeHttp();

        $this->send(['type' => 'image', 'media_url' => 'ftp://files.example.test/photo.jpg'])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    // --- Other checks still apply, before any download ----------------------

    public function test_nothing_is_downloaded_when_the_instance_is_not_connected(): void
    {
        $this->fakeHttp();
        $this->instance->update(['status' => 'disconnected']);

        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/photo.jpg'])
            ->assertStatus(422)
            ->assertJson(['error' => 'Instance is not connected']);

        Http::assertNothingSent();
    }

    public function test_a_worker_failure_marks_the_message_failed(): void
    {
        $this->fakeHttp(self::JPEG, 200, 502);

        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/photo.jpg'])
            ->assertStatus(502);

        $this->assertSame('failed', Message::sole()->status);
    }

    // --- Where it shows up -----------------------------------------------------

    public function test_status_api_reports_the_type(): void
    {
        $this->fakeHttp(self::JPEG);
        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/photo.jpg'])->assertOk();

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/messages/WA-MEDIA-1')
            ->assertOk()
            ->assertJsonPath('message.type', 'image');
    }

    public function test_api_logs_show_the_media_request(): void
    {
        $this->fakeHttp(self::JPEG);
        $this->send(['type' => 'image', 'media_url' => 'https://files.example.test/photo.jpg', 'message' => 'Caption'])->assertOk();

        $curl = Message::sole()->apiCurlExample();

        $this->assertStringContainsString('"type": "image"', $curl);
        $this->assertStringContainsString('"media_url"', $curl);
        $this->assertStringContainsString('"message": "Caption"', $curl);
    }

    public function test_dashboard_test_button_can_send_media(): void
    {
        $this->fakeHttp(self::JPEG);
        $user = $this->instance->user;

        $this->actingAs($user)->post(route('instances.send-test-message', $this->instance), [
            'to' => '919999999999',
            'type' => 'image',
            'media_url' => 'https://files.example.test/photo.jpg',
            'message' => 'From the dashboard',
        ])->assertRedirect(route('instances.show', $this->instance))
            ->assertSessionHas('status', 'Message sent.');

        $message = Message::sole();
        $this->assertSame('image', $message->type);
        $this->assertNull($message->api_token_id);
    }

    // --- Direct file uploads (instead of media_url) -----------------------

    public function test_api_accepts_an_uploaded_image(): void
    {
        $this->fakeHttp();

        $this->send([
            'type' => 'image',
            'message' => 'Uploaded photo',
            'media' => UploadedFile::fake()->createWithContent('holiday.jpg', self::JPEG),
        ])->assertOk();

        $message = Message::sole();
        $this->assertSame('image', $message->type);
        $this->assertSame('image/jpeg', $message->media_mime_type);
        $this->assertSame('Uploaded photo', $message->body);
        Storage::disk('whatsapp_media')->assertExists($message->media_path);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'files.example.test')); // nothing downloaded
        $this->assertSame($message->media_path, $this->workerRequest()['media']['path']);
    }

    public function test_an_uploaded_document_keeps_its_original_name(): void
    {
        $this->fakeHttp();

        $this->send([
            'type' => 'document',
            'media' => UploadedFile::fake()->createWithContent('Quote March.pdf', self::PDF),
        ])->assertOk();

        $this->assertSame('Quote March.pdf', Message::sole()->media_file_name);
    }

    public function test_media_url_and_an_upload_together_are_rejected(): void
    {
        $this->fakeHttp();

        $this->send([
            'type' => 'image',
            'media_url' => 'https://files.example.test/photo.jpg',
            'media' => UploadedFile::fake()->createWithContent('a.jpg', self::JPEG),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['media_url' => 'Send either media_url or a media file, not both.']);
    }

    public function test_an_upload_with_a_text_message_is_rejected(): void
    {
        $this->fakeHttp();

        $this->send([
            'message' => 'hi',
            'media' => UploadedFile::fake()->createWithContent('a.jpg', self::JPEG),
        ])->assertStatus(422)->assertJsonValidationErrors('media');
    }

    public function test_an_uploaded_file_of_the_wrong_type_is_rejected(): void
    {
        $this->fakeHttp();

        $this->send([
            'type' => 'image',
            'media' => UploadedFile::fake()->createWithContent('not-really.jpg', self::PDF),
        ])->assertStatus(422)
            ->assertJson(['error' => "The uploaded file is application/pdf, which can't be sent as image."]);

        $this->assertSame([], Storage::disk('whatsapp_media')->allFiles());
    }

    public function test_an_uploaded_file_over_the_type_limit_is_rejected(): void
    {
        $this->fakeHttp();

        $this->send([
            'type' => 'image',
            'media' => UploadedFile::fake()->createWithContent('big.jpg', self::JPEG.str_repeat("\0", 5 * 1024 * 1024)),
        ])->assertStatus(422)
            ->assertJson(['error' => 'The uploaded file is too large (max 5 MB for this type).']);
    }

    public function test_an_upload_that_php_rejected_gets_a_clear_error(): void
    {
        $this->fakeHttp();
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        // What PHP hands over when the file exceeded upload_max_filesize.
        $failed = new UploadedFile($tmp, 'huge.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->send(['type' => 'image', 'media' => $failed])
            ->assertStatus(422)
            ->assertJsonPath('error', fn (string $error) => str_starts_with($error, 'The file could not be uploaded'));

        @unlink($tmp);
    }

    public function test_a_request_over_post_max_size_gets_a_clear_json_error(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => (string) (1024 * 1024 * 1024)]) // 1 GB
            ->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/messages/send', [])
            ->assertStatus(413)
            ->assertJson(['success' => false])
            ->assertJsonPath('error', fn (string $error) => str_starts_with($error, 'The file is too large to upload'));
    }

    public function test_dashboard_accepts_a_dropped_file(): void
    {
        $this->fakeHttp();

        $this->actingAs($this->instance->user)->post(route('instances.send-test-message', $this->instance), [
            'to' => '919999999999',
            'type' => 'document',
            'message' => 'From my laptop',
            'media' => UploadedFile::fake()->createWithContent('Brochure.pdf', self::PDF),
        ])->assertSessionHas('status', 'Message sent.');

        $message = Message::sole();
        $this->assertSame('document', $message->type);
        $this->assertSame('Brochure.pdf', $message->media_file_name);
    }

    public function test_dashboard_needs_a_file_or_a_link_for_media(): void
    {
        $this->fakeHttp();

        $this->actingAs($this->instance->user)
            ->from(route('instances.show', $this->instance))
            ->post(route('instances.send-test-message', $this->instance), ['to' => '919999999999', 'type' => 'image'])
            ->assertSessionHasErrors(['media_url' => 'Drop a file, choose one from your computer, or paste a link to it.']);
    }

    public function test_dashboard_shows_the_drop_zone(): void
    {
        $this->actingAs($this->instance->user)->get(route('instances.show', $this->instance))
            ->assertOk()
            ->assertSee('Drag &amp; drop a file here', escape: false)
            ->assertSee('enctype="multipart/form-data"', escape: false);
    }

    public function test_dashboard_shows_a_clear_message_when_the_upload_is_over_post_max_size(): void
    {
        $this->actingAs($this->instance->user)
            ->from(route('instances.show', $this->instance))
            ->withServerVariables(['CONTENT_LENGTH' => (string) (1024 * 1024 * 1024)])
            ->post(route('instances.send-test-message', $this->instance), [])
            ->assertRedirect(route('instances.show', $this->instance))
            ->assertSessionHas('error', fn (string $error) => str_starts_with($error, 'The file is too large to upload'));
    }

    public function test_dashboard_test_button_shows_a_media_error(): void
    {
        $this->fakeHttp(self::PDF);

        $this->actingAs($this->instance->user)->post(route('instances.send-test-message', $this->instance), [
            'to' => '919999999999',
            'type' => 'image',
            'media_url' => 'https://files.example.test/photo.jpg',
        ])->assertSessionHas('error', "The file at media_url is application/pdf, which can't be sent as image.");

        $this->assertSame(0, Message::count());
    }
}
