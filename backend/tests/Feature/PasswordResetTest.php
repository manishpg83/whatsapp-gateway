<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The pages use @vite(); skip loading the built CSS/JS files in tests.
        $this->withoutVite();
    }

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_logged_in_users_cannot_view_the_forgot_password_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/forgot-password')
            ->assertRedirect(route('dashboard'));
    }

    public function test_requesting_a_link_for_a_real_email_sends_a_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/forgot-password', ['email' => 'jane@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_requesting_a_link_for_an_unknown_email_shows_the_same_status_message(): void
    {
        Notification::fake();

        // No user with this email exists at all.
        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect()->assertSessionHas('status');

        // Same generic message either way — CLAUDE.md §10: no account
        // enumeration. Also confirms no notification actually went out.
        $this->assertSame(
            "If an account exists for that email, we've sent a password reset link.",
            session('status')
        );
        Notification::assertNothingSent();
    }

    public function test_reset_password_page_renders(): void
    {
        $this->get('/reset-password/some-token?email=jane@example.com')->assertOk();
    }

    public function test_user_can_reset_their_password_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'old-password-123']);

        $this->post('/forgot-password', ['email' => 'jane@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'jane@example.com',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertRedirect(route('login'));

        // Old password no longer works, new one does.
        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'old-password-123'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'brand-new-password-456'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'jane@example.com',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertSessionHasErrors('email');

        // Password hash is completely untouched.
        $this->assertSame($user->password, $user->fresh()->password);
    }
}
