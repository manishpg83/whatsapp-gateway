<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SubscriptionActivated;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionRenewalReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SubscriptionEmailsTest extends TestCase
{
    use RefreshDatabase;

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    /** A correctly signed Cashfree webhook (same as CashfreeWebhookTest). */
    private function postSignedWebhook(array $payload): TestResponse
    {
        $rawBody = json_encode($payload);
        $timestamp = time();
        $signature = base64_encode(hash_hmac('sha256', $timestamp.$rawBody, config('services.cashfree.client_secret'), true));

        return $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        ], $rawBody);
    }

    /** SUBSCRIPTION_STATUS_CHANGED in the shape Cashfree documents (2025-01-01). */
    private function statusChanged(string $status, ?string $nextScheduleDate = null): array
    {
        return [
            'type' => 'SUBSCRIPTION_STATUS_CHANGED',
            'event_time' => '2026-09-30T10:30:00+05:30',
            'data' => [
                'subscription_details' => [
                    'subscription_id' => 'sub_mail_1',
                    'subscription_status' => $status,
                    'next_schedule_date' => $nextScheduleDate,
                ],
            ],
        ];
    }

    private function paidUser(string $status = 'active', string $plan = 'starter'): User
    {
        $user = User::factory()->create();
        $user->subscription->update([
            'plan' => $plan,
            'status' => $status,
            'cashfree_subscription_id' => 'sub_mail_1',
        ]);

        return $user;
    }

    // ---- Cancelled email

    public function test_cancelling_from_the_billing_page_sends_a_cancelled_email(): void
    {
        Notification::fake();
        Http::fake(['*' => Http::response(['subscription_status' => 'CANCELLED'], 200)]);
        $user = $this->paidUser(plan: 'growth');

        $this->actingAs($user)->post(route('billing.cancel'))->assertRedirect(route('billing.index'));

        Notification::assertSentTo($user, SubscriptionCancelled::class, fn ($n) => $n->planName === 'Growth');
    }

    public function test_cashfree_reporting_a_cancellation_sends_a_cancelled_email(): void
    {
        Notification::fake();
        $user = $this->paidUser();

        $this->postSignedWebhook($this->statusChanged('CANCELLED'))->assertNoContent();

        $this->assertSame('cancelled', $user->subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionCancelled::class, fn ($n) => $n->planName === 'Starter');
    }

    public function test_a_repeated_cancellation_webhook_does_not_email_twice(): void
    {
        Notification::fake();
        $user = $this->paidUser(status: 'cancelled');

        $this->postSignedWebhook($this->statusChanged('CANCELLED'))->assertNoContent();

        Notification::assertNothingSentTo($user);
    }

    // ---- Documented webhook shape (data.subscription_details.*)

    public function test_documented_status_changed_shape_activates_a_pending_subscription(): void
    {
        Notification::fake();
        $user = $this->paidUser(status: 'pending');

        $this->postSignedWebhook($this->statusChanged('ACTIVE'))->assertNoContent();

        $this->assertSame('active', $user->subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionActivated::class);
    }

    // ---- Renewal date

    public function test_a_successful_payment_sets_the_renewal_date_one_month_later(): void
    {
        $user = $this->paidUser();

        $this->postSignedWebhook([
            'type' => 'SUBSCRIPTION_PAYMENT_SUCCESS',
            'event_time' => '2026-09-30T10:30:00+05:30',
            'data' => ['cf_payment_id' => '555', 'payment_amount' => 749, 'subscription_id' => 'sub_mail_1'],
        ])->assertNoContent();

        // 10:30 IST = 05:00 UTC, plus one month.
        $this->assertSame('2026-10-30 05:00:00', $user->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
    }

    public function test_cashfree_next_schedule_date_sets_the_renewal_date(): void
    {
        $user = $this->paidUser();

        $this->postSignedWebhook($this->statusChanged('ACTIVE', '2026-10-15T10:30:00'))->assertNoContent();

        // No offset = IST; stored in UTC.
        $this->assertSame('2026-10-15 05:00:00', $user->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
    }

    // ---- Renewal reminder command

    public function test_reminder_is_sent_when_the_plan_renews_within_3_days(): void
    {
        Notification::fake();
        $user = $this->paidUser();
        $user->subscription->update(['current_period_end' => now()->addDays(2)]);

        $this->artisan('billing:renewal-reminders')->expectsOutput('Renewal reminders sent: 1')->assertSuccessful();

        Notification::assertSentTo($user, SubscriptionRenewalReminder::class,
            fn ($n) => $n->planName === 'Starter' && $n->price === 749);
    }

    public function test_reminder_is_sent_only_once_per_renewal(): void
    {
        Notification::fake();
        $user = $this->paidUser();
        $user->subscription->update(['current_period_end' => now()->addDays(2)]);

        $this->artisan('billing:renewal-reminders')->assertSuccessful();
        $this->artisan('billing:renewal-reminders')->expectsOutput('Renewal reminders sent: 0')->assertSuccessful();

        Notification::assertSentToTimes($user, SubscriptionRenewalReminder::class, 1);
    }

    public function test_no_reminder_when_renewal_is_further_away_or_not_active(): void
    {
        Notification::fake();
        $later = $this->paidUser();
        $later->subscription->update(['current_period_end' => now()->addDays(10)]);

        $cancelled = User::factory()->create();
        $cancelled->subscription->update([
            'plan' => 'starter',
            'status' => 'cancelled',
            'cashfree_subscription_id' => 'sub_mail_2',
            'current_period_end' => now()->addDay(),
        ]);

        $free = User::factory()->create();
        $free->subscription->update(['current_period_end' => now()->addDay()]);

        $this->artisan('billing:renewal-reminders')->expectsOutput('Renewal reminders sent: 0')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_renewal_reminder_email_content(): void
    {
        $user = User::factory()->create(['name' => 'Asha']);
        $mail = (new SubscriptionRenewalReminder('Starter', 749, now()->setDate(2026, 10, 30)->setTime(5, 0)))->toMail($user);

        $this->assertSame('Your Starter plan renews on October 30, 2026', $mail->subject);
        $this->assertStringContainsString('₹749 will be charged', (string) $mail->render());
    }
}
