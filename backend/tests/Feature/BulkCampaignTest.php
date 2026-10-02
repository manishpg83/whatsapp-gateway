<?php

namespace Tests\Feature;

use App\Http\Controllers\BulkCampaignController;
use App\Jobs\SendBulkMessage;
use App\Models\BulkCampaign;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bulk messages. The Free plan allows 50 messages a month (test config),
 * and 50 numbers per campaign (config/bulk.php).
 */
class BulkCampaignTest extends TestCase
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

    private function form(WhatsappSession $instance, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Diwali offer',
            'instance_id' => $instance->instance_id,
            'message' => 'Happy Diwali! 20% off this week.',
            'numbers' => "919876543210\n+91 98123-45678\n919876543210",
            'interval' => 10,
            'consent' => '1',
        ], $overrides);
    }

    /**
     * A running campaign with these numbers, without going through the form.
     */
    private function campaign(?WhatsappSession $instance = null, array $numbers = ['919876543210', '919812345678']): BulkCampaign
    {
        $instance ??= WhatsappSession::factory()->connected()->create();

        $campaign = $instance->user->bulkCampaigns()->create([
            'whatsapp_session_id' => $instance->id,
            'name' => 'Test',
            'body' => 'Hello',
            'interval_seconds' => 10,
            'status' => 'running',
            'run_token' => 'token-1',
            'run_started_at' => now()->subMinute(),
            'started_at' => now()->subMinute(),
        ]);

        foreach ($numbers as $phone) {
            $campaign->recipients()->create(['phone' => $phone]);
        }

        return $campaign;
    }

    private function runJob(BulkCampaign $campaign, string $token = 'token-1'): void
    {
        (new SendBulkMessage($campaign->id, $token))->handle(app(MessageSender::class), app(PlanLimiter::class));
    }

    // --- Access -------------------------------------------------------------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/bulk')->assertRedirect(route('login'));
        $this->get('/bulk/create')->assertRedirect(route('login'));
    }

    public function test_pages_load_for_the_owner(): void
    {
        $campaign = $this->campaign();
        $owner = $campaign->user;

        $this->actingAs($owner)->get('/bulk')->assertOk()->assertSee('Test');
        $this->actingAs($owner)->get('/bulk/create')->assertOk()->assertSee('Start sending');
        $this->actingAs($owner)->get("/bulk/{$campaign->campaign_id}")->assertOk()->assertSee('919876543210');
    }

    public function test_another_users_campaign_is_a_404(): void
    {
        $campaign = $this->campaign();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/bulk/{$campaign->campaign_id}")->assertNotFound();
        $this->actingAs($stranger)->get("/bulk/{$campaign->campaign_id}/status")->assertNotFound();
        $this->actingAs($stranger)->post("/bulk/{$campaign->campaign_id}/pause")->assertNotFound();
        $this->actingAs($stranger)->post("/bulk/{$campaign->campaign_id}/cancel")->assertNotFound();

        $this->assertSame('running', $campaign->fresh()->status);
    }

    // --- Creating -------------------------------------------------------------

    public function test_creating_a_campaign_cleans_the_numbers_and_starts_sending(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $response = $this->actingAs($instance->user)->post('/bulk', $this->form($instance));

        $campaign = BulkCampaign::sole();
        $response->assertRedirect(route('bulk.show', $campaign));

        $this->assertSame('running', $campaign->status);
        $this->assertSame(10, $campaign->interval_seconds);
        $this->assertSame(['919876543210', '919812345678'], $campaign->recipients()->orderBy('id')->pluck('phone')->all());
        Queue::assertPushed(SendBulkMessage::class, fn (SendBulkMessage $job) => $job->campaignId === $campaign->id && $job->runToken === $campaign->run_token);
    }

    public function test_invalid_numbers_are_listed(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['numbers' => "919876543210\nabc\n12"]))
            ->assertSessionHasErrors(['numbers' => 'These are not valid phone numbers: abc, 12. Use the full number with country code, digits only (e.g. 919876543210).']);

        $this->assertDatabaseCount('bulk_campaigns', 0);
    }

    public function test_consent_is_required(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['consent' => null]))
            ->assertSessionHasErrors('consent');
    }

    public function test_only_your_own_connected_instance_can_be_used(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        $foreign = WhatsappSession::factory()->connected()->create();
        $offline = WhatsappSession::factory()->create(['user_id' => $instance->user_id, 'status' => 'disconnected']);

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['instance_id' => $foreign->instance_id]))
            ->assertSessionHasErrors('instance_id');
        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['instance_id' => $offline->instance_id]))
            ->assertSessionHasErrors('instance_id');

        $this->assertDatabaseCount('bulk_campaigns', 0);
    }

    public function test_the_interval_must_be_one_of_the_options(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['interval' => 1]))
            ->assertSessionHasErrors('interval');
    }

    public function test_free_plan_is_limited_to_50_numbers(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        $numbers = implode("\n", array_map(fn ($i) => '9198765'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), range(1, 51)));

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['numbers' => $numbers]))
            ->assertSessionHasErrors(['numbers' => 'Your plan allows up to 50 numbers per campaign — you added 51.']);
    }

    public function test_a_campaign_must_fit_in_the_remaining_monthly_messages(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        Message::factory()->count(49)->create(['whatsapp_session_id' => $instance->id, 'direction' => 'outgoing']);

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance))
            ->assertSessionHasErrors(['numbers' => 'You have 1 messages left this month, but added 2 numbers. Remove some, or upgrade your plan.']);
    }

    public function test_only_one_running_campaign_per_instance(): void
    {
        $running = $this->campaign();

        $this->actingAs($running->user)->post('/bulk', $this->form($running->whatsappSession))
            ->assertSessionHasErrors('instance_id');

        $this->assertDatabaseCount('bulk_campaigns', 1);
    }

    public function test_number_parsing(): void
    {
        [$valid, $invalid] = BulkCampaignController::parseRecipients("+91 98765 43210, 919876543210;\n(415) 555-0123\n\nhello");

        $this->assertSame(['919876543210', '4155550123'], array_map('strval', array_keys($valid)));
        $this->assertSame([null, null], array_values($valid));
        $this->assertSame(['hello'], $invalid);
    }

    // --- Names and CSV (B3) -------------------------------------------------------

    public function test_parsing_names_from_csv_excel_and_pasted_lines(): void
    {
        $csv = "\xEF\xBB\xBFPhone Number,Name\n"     // Excel BOM + header row (skipped)
            ."919876543210,Rahul\n"
            ."\"+91 98123 45678\",\"Sharma, Priya\"\n" // quoted name with a comma
            ."14155550123\t Asha \n"                 // copied from Excel (tab)
            ."919876543210,Someone else\n"           // duplicate: first name kept
            ."919800000001\n";                        // no name

        [$valid, $invalid] = BulkCampaignController::parseRecipients($csv);

        $this->assertSame([], $invalid);
        $this->assertSame([
            '919876543210' => 'Rahul',
            '919812345678' => 'Sharma, Priya',
            '14155550123' => 'Asha',
            '919800000001' => null,
        ], array_combine(array_map('strval', array_keys($valid)), array_values($valid)));
    }

    public function test_a_csv_upload_creates_recipients_with_names(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'numbers' => null,
            'recipients_source' => 'csv',
            'csv' => UploadedFile::fake()->createWithContent('list.csv', "phone,name\n919876543210,Rahul\n919812345678,\n"),
            'message' => 'Hi {name}, sale is on!',
            'name_fallback' => 'there',
        ]))->assertSessionHasNoErrors();

        $campaign = BulkCampaign::sole();
        $this->assertSame('there', $campaign->name_fallback);
        $this->assertSame(
            ['919876543210' => 'Rahul', '919812345678' => null],
            $campaign->recipients()->orderBy('id')->pluck('name', 'phone')->all(),
        );
    }

    public function test_csv_problems_are_shown_under_the_csv_box(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['numbers' => null, 'recipients_source' => 'csv']))
            ->assertSessionHasErrors(['csv' => 'Choose the CSV file with your numbers.']);

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'recipients_source' => 'csv',
            'csv' => UploadedFile::fake()->createWithContent('list.csv', "919876543210\n9.19876E+11\n"), // Excel number mangling
        ]))->assertSessionHasErrors(['csv']);

        $this->assertDatabaseCount('bulk_campaigns', 0);
    }

    public function test_the_job_puts_each_name_into_the_message(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['message_id' => 'WA-1'], 200)]);
        $campaign = $this->campaign(numbers: []);
        $campaign->update(['body' => 'Hi {name}, sale is on!', 'name_fallback' => 'there']);
        $campaign->recipients()->create(['phone' => '919876543210', 'name' => 'Rahul']);
        $campaign->recipients()->create(['phone' => '919812345678']);

        $this->runJob($campaign);
        $this->runJob($campaign);

        $bodies = $campaign->recipients()->orderBy('id')->with('message')->get()->pluck('message.body')->all();
        $this->assertSame(['Hi Rahul, sale is on!', 'Hi there, sale is on!'], $bodies);
    }

    public function test_without_a_name_or_fallback_the_gap_is_tidied(): void
    {
        $campaign = new BulkCampaign(['body' => 'Hi {name}, sale is on!']);

        $this->assertSame('Hi, sale is on!', $campaign->bodyFor(null));
        $this->assertSame('Hi Asha, sale is on!', $campaign->bodyFor('Asha'));
        $this->assertSame('No placeholder', (new BulkCampaign(['body' => 'No placeholder']))->bodyFor('Asha'));
    }

    public function test_the_sample_csv_downloads(): void
    {
        $this->actingAs(User::factory()->create())->get('/bulk/sample.csv')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="bulk-numbers-sample.csv"')
            ->assertSee('phone,name');
    }

    // --- The sending job --------------------------------------------------------

    public function test_the_job_sends_the_next_number_and_queues_the_next_one_later(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['message_id' => 'WA-1'], 200)]);
        $campaign = $this->campaign();

        $this->runJob($campaign);

        $first = $campaign->recipients()->orderBy('id')->first();
        $this->assertSame('sent', $first->status);
        $this->assertSame('WA-1', $first->message->whatsapp_message_id);
        $this->assertSame('pending', $campaign->recipients()->orderBy('id')->skip(1)->first()->status);

        Queue::assertPushed(SendBulkMessage::class, fn (SendBulkMessage $job) => $job->runToken === 'token-1' && $job->delay !== null);
        Http::assertSentCount(1);
    }

    public function test_the_campaign_completes_after_the_last_number(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['message_id' => 'WA-1'], 200)]);
        $campaign = $this->campaign(numbers: ['919876543210']);

        $this->runJob($campaign);

        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->finished_at);
        Queue::assertNothingPushed();
    }

    public function test_an_old_job_after_pause_and_resume_sends_nothing(): void
    {
        Http::fake();
        $campaign = $this->campaign();
        $campaign->update(['run_token' => 'token-2']); // resumed meanwhile

        $this->runJob($campaign, 'token-1');

        Http::assertNothingSent();
        $this->assertSame(2, $campaign->recipients()->where('status', 'pending')->count());
    }

    public function test_a_paused_campaign_sends_nothing(): void
    {
        Http::fake();
        $campaign = $this->campaign();
        $campaign->pause();

        $this->runJob($campaign, 'token-1');

        Http::assertNothingSent();
    }

    public function test_it_pauses_itself_when_the_instance_is_offline(): void
    {
        Http::fake();
        $campaign = $this->campaign();
        $campaign->whatsappSession->update(['status' => 'disconnected']);

        $this->runJob($campaign);

        Http::assertNothingSent();
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertStringContainsString('not connected', $campaign->fresh()->pause_reason);
    }

    public function test_it_pauses_itself_at_the_monthly_limit(): void
    {
        Http::fake();
        $campaign = $this->campaign();
        Message::factory()->count(50)->create(['whatsapp_session_id' => $campaign->whatsapp_session_id, 'direction' => 'outgoing']);

        $this->runJob($campaign);

        Http::assertNothingSent();
        $this->assertSame('paused', $campaign->fresh()->status);
    }

    public function test_it_pauses_itself_after_five_failures_in_a_row(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'not on WhatsApp'], 400)]);
        $campaign = $this->campaign(numbers: array_map(fn ($i) => "9198765432{$i}0", range(1, 7)));

        foreach (range(1, 5) as $ignored) {
            $this->runJob($campaign);
        }

        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertSame(5, $campaign->recipients()->where('status', 'failed')->count());
        $this->assertSame(2, $campaign->recipients()->where('status', 'pending')->count());
    }

    public function test_a_missing_failure_setting_does_not_pause_after_a_successful_send(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['message_id' => 'WA-1'], 200)]);
        config(['bulk' => null]); // e.g. a queue worker started before config/bulk.php existed
        $campaign = $this->campaign();

        $this->runJob($campaign);

        $this->assertSame('running', $campaign->fresh()->status);
        Queue::assertPushed(SendBulkMessage::class);
    }

    // --- Image / video / document (B2) -------------------------------------------

    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    public function test_an_image_campaign_stores_the_file_once_with_an_optional_caption(): void
    {
        Queue::fake();
        Storage::fake('whatsapp_media');
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'type' => 'image',
            'message' => '',
            'media' => UploadedFile::fake()->createWithContent('offer.jpg', self::JPEG),
        ]))->assertSessionHasNoErrors();

        $campaign = BulkCampaign::sole();
        $this->assertSame('image', $campaign->type);
        $this->assertSame('', $campaign->body);
        $this->assertSame('image/jpeg', $campaign->media_mime_type);
        Storage::disk('whatsapp_media')->assertExists($campaign->media_path);
        $this->assertCount(1, Storage::disk('whatsapp_media')->allFiles());
    }

    public function test_a_media_campaign_needs_a_file_of_the_right_kind(): void
    {
        Storage::fake('whatsapp_media');
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['type' => 'image', 'media' => null]))
            ->assertSessionHasErrors(['media' => 'Choose the file to send.']);

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'type' => 'image',
            'media' => UploadedFile::fake()->createWithContent('not-really.jpg', self::PDF),
        ]))->assertSessionHasErrors('media');

        $this->assertDatabaseCount('bulk_campaigns', 0);
        $this->assertSame([], Storage::disk('whatsapp_media')->allFiles());
    }

    public function test_no_file_is_stored_when_the_rest_of_the_form_is_invalid(): void
    {
        Storage::fake('whatsapp_media');
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'type' => 'document',
            'numbers' => 'not-a-number',
            'media' => UploadedFile::fake()->createWithContent('Price list.pdf', self::PDF),
        ]))->assertSessionHasErrors('numbers');

        $this->assertSame([], Storage::disk('whatsapp_media')->allFiles());
    }

    public function test_the_job_sends_the_campaign_file_to_every_number(): void
    {
        Queue::fake();
        Storage::fake('whatsapp_media');
        Http::fake(['*' => Http::response(['message_id' => 'WA-1'], 200)]);
        Storage::disk('whatsapp_media')->put('inst/out-1.pdf', self::PDF);

        $campaign = $this->campaign();
        $campaign->update([
            'type' => 'document',
            'body' => 'Our new price list',
            'media_path' => 'inst/out-1.pdf',
            'media_mime_type' => 'application/pdf',
            'media_file_name' => 'Price list.pdf',
            'media_size' => strlen(self::PDF),
        ]);

        $this->runJob($campaign);

        $message = $campaign->recipients()->orderBy('id')->first()->message;
        $this->assertSame('document', $message->type);
        $this->assertSame('Our new price list', $message->body);
        $this->assertSame('inst/out-1.pdf', $message->media_path);
        $this->assertSame('Price list.pdf', $message->media_file_name);

        Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'document');
    }

    public function test_the_campaign_file_is_only_served_to_its_owner(): void
    {
        Storage::fake('whatsapp_media');
        Storage::disk('whatsapp_media')->put('inst/out-1.pdf', self::PDF);
        $campaign = $this->campaign();
        $campaign->update(['type' => 'document', 'media_path' => 'inst/out-1.pdf', 'media_mime_type' => 'application/pdf', 'media_file_name' => 'List.pdf']);

        $this->actingAs($campaign->user)->get("/bulk/{$campaign->campaign_id}/media")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream'); // documents always download

        $this->actingAs(User::factory()->create())->get("/bulk/{$campaign->campaign_id}/media")->assertNotFound();
    }

    // --- Pause / resume / cancel -------------------------------------------------

    public function test_pause_resume_and_cancel(): void
    {
        Queue::fake();
        $campaign = $this->campaign();
        $owner = $campaign->user;
        $url = "/bulk/{$campaign->campaign_id}";

        $this->actingAs($owner)->post("{$url}/pause")->assertRedirect(route('bulk.show', $campaign));
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->run_token);

        $this->actingAs($owner)->post("{$url}/resume")->assertRedirect(route('bulk.show', $campaign));
        $campaign->refresh();
        $this->assertSame('running', $campaign->status);
        $this->assertNotSame('token-1', $campaign->run_token);
        Queue::assertPushed(SendBulkMessage::class, fn (SendBulkMessage $job) => $job->runToken === $campaign->run_token);

        $this->actingAs($owner)->post("{$url}/cancel")->assertRedirect(route('bulk.show', $campaign));
        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertSame(2, $campaign->recipients()->where('status', 'skipped')->count());
    }

    public function test_resume_is_refused_while_the_instance_is_offline(): void
    {
        Queue::fake();
        $campaign = $this->campaign();
        $campaign->pause('offline');
        $campaign->whatsappSession->update(['status' => 'disconnected']);

        $this->actingAs($campaign->user)->post("/bulk/{$campaign->campaign_id}/resume")
            ->assertSessionHas('error');

        $this->assertSame('paused', $campaign->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_the_status_endpoint_returns_counts(): void
    {
        $campaign = $this->campaign();
        $campaign->recipients()->first()->update(['status' => 'sent']);

        $this->actingAs($campaign->user)->getJson("/bulk/{$campaign->campaign_id}/status")
            ->assertOk()
            ->assertJson(['status' => 'running', 'counts' => ['total' => 2, 'sent' => 1, 'pending' => 1, 'done' => 1]]);
    }
}
