<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Notifications\UsageLimitWarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Emails at 80% / 100% of the monthly message limit (UsageWarner).
 * The Free plan's limit is 50 messages/month (config/plans.php), so 80% = 40.
 */
class UsageWarningTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappSession $instance;

    private string $token;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Http::fake(['*' => Http::response(['message_id' => 'WA-1'], 200)]);

        $this->instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $this->token] = ApiToken::generateFor($this->instance, 'x');
    }

    private function alreadySent(int $count): void
    {
        Message::factory()->for($this->instance, 'whatsappSession')->count($count)->create([
            'direction' => 'outgoing', 'status' => 'sent', 'created_at' => now(),
        ]);
    }

    private function sendOne(): TestResponse
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/messages/send', [
                'instance_id' => $this->instance->instance_id,
                'to' => '919999999999',
                'message' => 'hi',
            ]);
    }

    private function owner(): User
    {
        return $this->instance->user;
    }

    public function test_no_email_below_80_percent(): void
    {
        $this->alreadySent(38);
        $this->sendOne()->assertOk(); // 39 of 50

        Notification::assertNothingSent();
    }

    public function test_emails_once_when_reaching_80_percent(): void
    {
        $this->alreadySent(39);
        $this->sendOne()->assertOk(); // 40 of 50 = 80%
        $this->sendOne()->assertOk(); // 41 — no second 80% email

        Notification::assertSentToTimes($this->owner(), UsageLimitWarning::class, 1);
        Notification::assertSentTo($this->owner(), UsageLimitWarning::class,
            fn ($n) => $n->percent === 80 && $n->used === 40 && $n->limit === 50 && $n->planName === 'Free');
    }

    public function test_emails_again_when_reaching_the_limit(): void
    {
        $this->alreadySent(39);
        $this->sendOne(); // 80%
        $this->alreadySent(9);
        $this->sendOne(); // 50 of 50 = 100%

        Notification::assertSentToTimes($this->owner(), UsageLimitWarning::class, 2);
        Notification::assertSentTo($this->owner(), UsageLimitWarning::class, fn ($n) => $n->percent === 100);

        // Over the limit the API refuses, and no more emails go out.
        $this->sendOne()->assertStatus(422);
        Notification::assertSentToTimes($this->owner(), UsageLimitWarning::class, 2);
    }

    public function test_jumping_straight_past_80_percent_sends_only_the_100_percent_email(): void
    {
        $this->alreadySent(49);
        $this->sendOne(); // 50 of 50

        Notification::assertSentToTimes($this->owner(), UsageLimitWarning::class, 1);
        Notification::assertSentTo($this->owner(), UsageLimitWarning::class, fn ($n) => $n->percent === 100);
    }

    public function test_warnings_start_fresh_next_month(): void
    {
        $this->alreadySent(39);
        $this->sendOne(); // 80% this month

        $this->travelTo(now()->startOfMonth()->addMonthNoOverflow()->addDay());
        $this->alreadySent(39);
        $this->sendOne(); // 80% again, new month

        Notification::assertSentToTimes($this->owner(), UsageLimitWarning::class, 2);
    }

    public function test_upgrading_mid_month_starts_fresh_against_the_new_limit(): void
    {
        $this->alreadySent(39);
        $this->sendOne(); // 80% of Free (50)

        $this->owner()->subscription->update(['plan' => 'starter', 'status' => 'active']); // 1,000/month
        $this->alreadySent(759);
        $this->sendOne(); // 800 of 1,000 = 80%

        Notification::assertSentToTimes($this->owner(), UsageLimitWarning::class, 2);
        Notification::assertSentTo($this->owner(), UsageLimitWarning::class, fn ($n) => $n->limit === 1000 && $n->planName === 'Starter');
    }

    public function test_dashboard_test_messages_count_too(): void
    {
        $this->alreadySent(39);

        $this->actingAs($this->owner())->post(route('instances.send-test-message', $this->instance), [
            'to' => '919999999999',
            'message' => 'hi',
        ]);

        Notification::assertSentTo($this->owner(), UsageLimitWarning::class, fn ($n) => $n->percent === 80);
    }

    public function test_email_content(): void
    {
        $mail = (new UsageLimitWarning(80, 40, 50, 'Free'))->toMail($this->owner());
        $this->assertSame("You've used 80% of your monthly messages", $mail->subject);
        $this->assertSame(route('billing.index'), $mail->actionUrl);

        $mail = (new UsageLimitWarning(100, 50, 50, 'Free'))->toMail($this->owner());
        $this->assertSame("You've reached your monthly message limit", $mail->subject);
        $this->assertSame('Upgrade plan', $mail->actionText);
    }
}
