<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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

    private function verificationUrl(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }

    public function test_registering_sends_a_verification_email_and_shows_the_notice(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'jane@example.com')->sole();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_notice_page_shows_the_users_email_and_a_resend_button(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'jane@example.com']);

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Check your email')
            ->assertSee('jane@example.com')
            ->assertSee('Resend verification email')
            ->assertSee('Log out');
    }

    public function test_unverified_user_is_sent_to_the_notice_from_every_app_page(): void
    {
        $user = User::factory()->unverified()->create();

        foreach (['dashboard', 'instances.index', 'messages.index', 'billing.index', 'docs.index', 'api-logs.index', 'account.edit'] as $route) {
            $this->actingAs($user)->get(route($route))->assertRedirect(route('verification.notice'));
        }
    }

    public function test_unverified_user_can_still_log_out(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_valid_link_verifies_the_email_and_goes_to_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Thanks — your email is verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_tampered_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user).'x')->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user, 60);

        $this->travel(61)->minutes();

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_with_the_wrong_email_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_another_users_link_cannot_verify_your_account_or_theirs(): void
    {
        $me = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($me)->get($this->verificationUrl($other))->assertForbidden();

        $this->assertFalse($me->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_guest_opening_the_link_is_asked_to_log_in_first(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_a_new_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'A new verification link has been sent to your email.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_is_limited_to_six_per_minute(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        }

        $this->actingAs($user)->post(route('verification.send'))->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
    }

    public function test_verified_user_is_not_shown_the_notice_or_sent_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('dashboard'));

        Notification::assertNothingSent();
    }

    public function test_verified_admin_is_sent_to_the_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('verification.notice'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_unverified_user_cannot_open_the_admin_panel(): void
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    }
}
