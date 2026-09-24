<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailChanged;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass-123',
        ]);
    }

    public function test_account_page_shows_the_profile_form(): void
    {
        $this->actingAs($this->user())->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Save profile')
            ->assertSee('value="Jane Doe"', escape: false)
            ->assertSee('value="jane@example.com"', escape: false);
    }

    // --- Name only ---------------------------------------------------------

    public function test_changing_only_the_name_needs_no_password_and_keeps_verification(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ])->assertRedirect(route('account.edit'))
            ->assertSessionHas('status', 'Profile updated.');

        $user->refresh();
        $this->assertSame('Jane Smith', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_name_is_required(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => '', 'email' => 'jane@example.com'])
            ->assertSessionHasErrorsIn('profile', 'name');

        $this->assertSame('Jane Doe', $user->fresh()->name);
    }

    // --- Email change --------------------------------------------------------

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'new@example.com',
        ])->assertSessionHasErrorsIn('profile', ['current_password' => 'Enter your current password to change your email address.']);

        $this->assertSame('jane@example.com', $user->fresh()->email);
    }

    public function test_changing_the_email_with_a_wrong_password_fails(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'new@example.com',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrorsIn('profile', 'current_password');

        $this->assertSame('jane@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_changing_the_email_requires_re_verification_and_notifies_both_addresses(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'New@Example.com', // stored lower-case
            'current_password' => 'secret-pass-123',
        ])->assertRedirect(route('verification.notice'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());

        // Verification link goes to the NEW address...
        Notification::assertSentTo($user, VerifyEmail::class);

        // ...and a heads-up goes to the OLD one.
        Notification::assertSentTo(
            new AnonymousNotifiable,
            EmailChanged::class,
            fn (EmailChanged $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'jane@example.com'
                && $notification->newEmail === 'new@example.com'
        );

        // Blocked from the app until the new address is verified.
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_the_old_addresses_password_reset_tokens_are_removed(): void
    {
        $user = $this->user();
        DB::table('password_reset_tokens')->insert(['email' => 'jane@example.com', 'token' => 'x', 'created_at' => now()]);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'new@example.com',
            'current_password' => 'secret-pass-123',
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'jane@example.com']);
    }

    public function test_cannot_take_an_email_another_account_uses(): void
    {
        $user = $this->user();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'current_password' => 'secret-pass-123',
        ])->assertSessionHasErrorsIn('profile', 'email');

        $this->assertSame('jane@example.com', $user->fresh()->email);
    }

    public function test_same_email_in_different_case_is_not_a_change(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'JANE@example.com',
        ])->assertRedirect(route('account.edit'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_the_notice_hides_most_of_the_new_address(): void
    {
        $this->assertSame('ne***@example.com', EmailChanged::mask('new.person@example.com'));
    }

    public function test_guest_cannot_update_a_profile(): void
    {
        $this->put(route('account.profile.update'), ['name' => 'X', 'email' => 'x@example.com'])
            ->assertRedirect(route('login'));
    }
}
