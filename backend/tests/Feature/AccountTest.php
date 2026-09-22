<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountTest extends TestCase
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

    public function test_guest_cannot_view_account_page(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_account_page_renders_with_the_users_details(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com');
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertRedirect(route('account.edit'));

        $this->assertTrue(Hash::check('brand-new-password-456', $user->fresh()->password));
    }

    public function test_changing_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_user_can_delete_their_account(): void
    {
        Http::fake(['*' => Http::response(['stopped' => true], 200)]);

        $user = User::factory()->create(['password' => 'my-password-123']);
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        ['token' => $token] = ApiToken::generateFor($instance, 'x');
        Message::factory()->for($instance, 'whatsappSession')->create();

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'current_password' => 'my-password-123',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertNull($user->fresh());
        $this->assertNull(WhatsappSession::find($instance->id));
        $this->assertNull($token->fresh());
        $this->assertSame(0, Message::where('whatsapp_session_id', $instance->id)->count());

        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:3001/sessions/{$instance->instance_id}");
    }

    public function test_deleting_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'my-password-123']);

        $this->actingAs($user)->delete(route('account.destroy'), [
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password', null, 'deleteAccount');

        $this->assertNotNull($user->fresh());
    }

    public function test_cannot_delete_account_with_an_active_paid_subscription(): void
    {
        $user = User::factory()->create(['password' => 'my-password-123']);
        $user->subscription->update(['plan' => 'starter', 'status' => 'active']);

        $this->actingAs($user)->delete(route('account.destroy'), [
            'current_password' => 'my-password-123',
        ])->assertSessionHas('error');

        $this->assertNotNull($user->fresh());
    }

    public function test_deletion_proceeds_even_if_the_worker_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $user = User::factory()->create(['password' => 'my-password-123']);
        WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->delete(route('account.destroy'), [
            'current_password' => 'my-password-123',
        ])->assertRedirect(route('login'));

        $this->assertNull($user->fresh());
    }
}
