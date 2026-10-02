<?php

namespace Tests\Feature;

use App\Jobs\SendBulkMessage;
use App\Jobs\StartScheduledBulkCampaign;
use App\Models\BulkCampaign;
use App\Models\BulkTemplate;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Bulk messages B4: scheduling a campaign for later, and saved messages.
 */
class BulkScheduleAndTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // 10:00 UTC = 15:30 in India.
        $this->travelTo(Carbon::parse('2026-10-02 10:00:00', 'UTC'));
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
            'message' => 'Happy Diwali, {name}!',
            'numbers' => "919876543210, Rahul\n919812345678",
            'interval' => 10,
            'consent' => '1',
        ], $overrides);
    }

    private function scheduledCampaign(?WhatsappSession $instance = null): BulkCampaign
    {
        $instance ??= WhatsappSession::factory()->connected()->create();

        $campaign = $instance->user->bulkCampaigns()->create([
            'whatsapp_session_id' => $instance->id,
            'name' => 'Later',
            'body' => 'Hello',
            'interval_seconds' => 10,
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
            'timezone' => 'Asia/Kolkata',
            'run_token' => 'token-1',
        ]);
        $campaign->recipients()->create(['phone' => '919876543210']);

        return $campaign;
    }

    // --- Scheduling ---------------------------------------------------------------

    public function test_a_campaign_can_be_scheduled_in_the_users_timezone(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'when' => 'later',
            'scheduled_at' => '2026-10-05T09:00',
            'timezone' => 'Asia/Kolkata',
        ]))->assertSessionHasNoErrors();

        $campaign = BulkCampaign::sole();
        $this->assertSame('scheduled', $campaign->status);
        $this->assertSame('2026-10-05 03:30:00', $campaign->scheduled_at->utc()->format('Y-m-d H:i:s')); // 09:00 IST
        $this->assertSame('09:00', $campaign->scheduledAtLocal()->format('H:i'));

        Queue::assertPushed(StartScheduledBulkCampaign::class, fn ($job) => $job->runToken === $campaign->run_token);
        Queue::assertNotPushed(SendBulkMessage::class);
    }

    public function test_the_india_timezone_is_used_when_the_browser_sends_none(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['when' => 'later', 'scheduled_at' => '2026-10-05T09:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Asia/Kolkata', BulkCampaign::sole()->timezone);
    }

    public function test_older_timezone_names_from_browsers_work(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'when' => 'later', 'scheduled_at' => '2026-10-05T09:00', 'timezone' => 'Asia/Calcutta',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-05 03:30:00', BulkCampaign::sole()->scheduled_at->utc()->format('Y-m-d H:i:s'));

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'when' => 'later', 'scheduled_at' => '2026-10-05T09:00', 'timezone' => 'Not/AZone',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Asia/Kolkata', BulkCampaign::latest('id')->first()->timezone);
    }

    public function test_past_and_too_far_times_are_rejected(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'when' => 'later', 'scheduled_at' => '2026-10-02T15:00', 'timezone' => 'Asia/Kolkata', // 30 min ago
        ]))->assertSessionHasErrors('scheduled_at');

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'when' => 'later', 'scheduled_at' => '2026-11-15T09:00', 'timezone' => 'Asia/Kolkata',
        ]))->assertSessionHasErrors(['scheduled_at' => 'You can schedule up to 30 days ahead.']);

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['when' => 'later']))
            ->assertSessionHasErrors(['scheduled_at' => 'Choose the date and time to send.']);

        $this->assertDatabaseCount('bulk_campaigns', 0);
    }

    public function test_a_campaign_can_be_scheduled_while_another_is_sending(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();
        $running = $this->scheduledCampaign($instance);
        $running->update(['status' => 'running']);

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'when' => 'later', 'scheduled_at' => '2026-10-05T09:00', 'timezone' => 'Asia/Kolkata',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bulk_campaigns', 2);
    }

    public function test_the_scheduled_start_starts_sending(): void
    {
        Queue::fake();
        $campaign = $this->scheduledCampaign();

        (new StartScheduledBulkCampaign($campaign->id, 'token-1'))->handle();

        $campaign->refresh();
        $this->assertSame('running', $campaign->status);
        Queue::assertPushed(SendBulkMessage::class, fn ($job) => $job->runToken === $campaign->run_token);
    }

    public function test_the_scheduled_start_does_nothing_after_send_now_or_cancel(): void
    {
        Queue::fake();
        $campaign = $this->scheduledCampaign();
        $campaign->cancel();

        (new StartScheduledBulkCampaign($campaign->id, 'token-1'))->handle();

        $this->assertSame('cancelled', $campaign->fresh()->status);
        Queue::assertNotPushed(SendBulkMessage::class);
    }

    public function test_the_scheduled_start_pauses_if_the_instance_is_offline(): void
    {
        Queue::fake();
        $campaign = $this->scheduledCampaign();
        $campaign->whatsappSession->update(['status' => 'disconnected']);

        (new StartScheduledBulkCampaign($campaign->id, 'token-1'))->handle();

        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertStringContainsString('not connected', $campaign->fresh()->pause_reason);
        Queue::assertNotPushed(SendBulkMessage::class);
    }

    public function test_the_scheduled_start_pauses_if_another_campaign_is_sending(): void
    {
        Queue::fake();
        $campaign = $this->scheduledCampaign();
        $other = $this->scheduledCampaign($campaign->whatsappSession);
        $other->update(['status' => 'running']);

        (new StartScheduledBulkCampaign($campaign->id, 'token-1'))->handle();

        $this->assertSame('paused', $campaign->fresh()->status);
    }

    public function test_send_now_and_cancel_on_a_scheduled_campaign(): void
    {
        Queue::fake();
        $campaign = $this->scheduledCampaign();
        $url = "/bulk/{$campaign->campaign_id}";

        $this->actingAs($campaign->user)->get($url)->assertOk()->assertSee('Scheduled for')->assertSee('Send now');

        $this->actingAs($campaign->user)->post("{$url}/resume")->assertSessionHas('status', 'Sending now instead of at the scheduled time.');
        $this->assertSame('running', $campaign->fresh()->status);
        $this->assertNotSame('token-1', $campaign->fresh()->run_token);

        $second = $this->scheduledCampaign();
        $this->actingAs($second->user)->post("/bulk/{$second->campaign_id}/cancel");
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame(1, $second->recipients()->where('status', 'skipped')->count());
    }

    // --- Saved messages -----------------------------------------------------------

    public function test_saved_messages_can_be_created_edited_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bulk/templates', ['name' => 'Diwali', 'body' => 'Happy Diwali, {name}!'])
            ->assertRedirect(route('bulk.templates.index'));
        $template = BulkTemplate::sole();

        $this->actingAs($user)->get('/bulk/templates')->assertOk()->assertSee('Happy Diwali, {name}!');
        $this->actingAs($user)->get("/bulk/templates/{$template->id}/edit")->assertOk();

        $this->actingAs($user)->put("/bulk/templates/{$template->id}", ['name' => 'Diwali 2026', 'body' => 'New text'])
            ->assertRedirect(route('bulk.templates.index'));
        $this->assertSame('Diwali 2026', $template->fresh()->name);

        $this->actingAs($user)->delete("/bulk/templates/{$template->id}")->assertRedirect(route('bulk.templates.index'));
        $this->assertDatabaseCount('bulk_templates', 0);
    }

    public function test_another_users_saved_message_is_a_404(): void
    {
        $template = User::factory()->create()->bulkTemplates()->create(['name' => 'Mine', 'body' => 'Hi']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/bulk/templates/{$template->id}/edit")->assertNotFound();
        $this->actingAs($stranger)->put("/bulk/templates/{$template->id}", ['name' => 'X', 'body' => 'X'])->assertNotFound();
        $this->actingAs($stranger)->delete("/bulk/templates/{$template->id}")->assertNotFound();
        $this->actingAs($stranger)->get('/bulk/templates')->assertDontSee('Mine');

        $this->assertSame('Mine', $template->fresh()->name);
    }

    public function test_saved_messages_are_limited_per_user(): void
    {
        $user = User::factory()->create();
        foreach (range(1, BulkTemplate::MAX_PER_USER) as $i) {
            $user->bulkTemplates()->create(['name' => "T{$i}", 'body' => 'Hi']);
        }

        $this->actingAs($user)->post('/bulk/templates', ['name' => 'One more', 'body' => 'Hi'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_campaigns_message_can_be_saved_and_is_offered_next_time(): void
    {
        Queue::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, [
            'save_template' => '1',
            'template_name' => 'Diwali greeting',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Happy Diwali, {name}!', $instance->user->bulkTemplates()->sole()->body);

        $this->actingAs($instance->user)->get('/bulk/create')
            ->assertOk()
            ->assertSee('Use a saved message')
            ->assertSee('Diwali greeting');
    }

    public function test_saving_a_campaigns_message_needs_a_name(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs($instance->user)->post('/bulk', $this->form($instance, ['save_template' => '1']))
            ->assertSessionHasErrors(['template_name' => 'Give the saved message a name.']);
    }
}
