<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
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

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'terms' => '1',
        ], $overrides);
    }

    public function test_register_requires_the_18_plus_and_terms_checkbox(): void
    {
        $data = $this->validRegistration();
        unset($data['terms']);

        $this->post('/register', $data)->assertSessionHasErrors('terms');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    // ---- Register ----------------------------------------------------------

    public function test_register_page_is_shown_to_guests(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', $this->validRegistration());

        // Logged in, but must verify their email first (see EmailVerificationTest).
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('Test User', $user->name);
    }

    public function test_password_is_stored_hashed_never_plain(): void
    {
        $this->post('/register', $this->validRegistration());

        $stored = User::where('email', 'test@example.com')->value('password');

        $this->assertNotSame('secret-pass-123', $stored);
        $this->assertTrue(Hash::check('secret-pass-123', $stored));
    }

    public function test_email_is_stored_lower_case(): void
    {
        $this->post('/register', $this->validRegistration(['email' => '  Test@Example.COM ']));

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->post('/register', $this->validRegistration())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_register_rejects_short_password(): void
    {
        $this->post('/register', $this->validRegistration([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_register_rejects_unconfirmed_password(): void
    {
        $this->post('/register', $this->validRegistration(['password_confirmation' => 'different-pass']))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_register_requires_name_and_valid_email(): void
    {
        $this->post('/register', $this->validRegistration(['name' => '', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['name', 'email']);
    }

    // ---- Login -------------------------------------------------------------

    public function test_login_page_is_shown_to_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_is_redirected_to_the_admin_panel_after_login(): void
    {
        $admin = User::factory()->create(['password' => 'secret-pass-123', 'is_admin' => true]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'secret-pass-123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_remember_me_checkbox_issues_a_persistent_remember_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123', 'remember_token' => null]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-pass-123',
            'remember' => 'on',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_without_remember_me_does_not_issue_a_remember_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123', 'remember_token' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123'])
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_login_gives_same_error_for_unknown_email(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever-123'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // Even the CORRECT password is refused while locked.
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    // ---- Logout & access rules --------------------------------------------

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logout_only_works_with_post(): void
    {
        $this->actingAs(User::factory()->create())->get('/logout')->assertStatus(405);
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_logged_in_user_can_see_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Jane Doe');
    }

    public function test_logged_in_user_is_redirected_away_from_login_and_register(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/register')->assertRedirect(route('dashboard'));
    }

    public function test_logged_in_admin_is_redirected_away_from_login_to_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/login')->assertRedirect(route('admin.dashboard'));
        $this->get('/register')->assertRedirect(route('admin.dashboard'));
    }
}
