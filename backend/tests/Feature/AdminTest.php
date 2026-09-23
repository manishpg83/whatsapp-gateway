<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_view_the_admin_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertNotFound();
    }

    public function test_regular_user_cannot_view_another_users_admin_detail_page(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.show', $other))->assertNotFound();
    }

    public function test_regular_user_does_not_see_the_admin_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee(route('admin.users.index'), escape: false);
    }

    public function test_admin_can_view_the_users_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com');
    }

    public function test_admin_sees_the_admin_link(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertSee(route('admin.users.index'), escape: false);
    }

    public function test_admin_can_view_another_users_detail_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $instance = WhatsappSession::factory()->for($other)->connected()->create(['name' => 'Jane\'s Instance']);

        Message::factory()->create([
            'whatsapp_session_id' => $instance->id,
            'direction' => 'outgoing',
            'status' => 'sent',
            'to_number' => '919999999999',
            'body' => 'a message only the owner should see the text of',
        ]);

        // Counts, yes — but never the actual message content or the
        // recipient's number (the whole point of the "counts only" scope).
        $this->actingAs($admin)->get(route('admin.users.show', $other))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com')
            ->assertSee('Jane\'s Instance')
            ->assertSee('connected')
            ->assertDontSee('a message only the owner should see the text of')
            ->assertDontSee('919999999999');
    }

    public function test_admin_users_index_does_not_expose_message_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $instance = WhatsappSession::factory()->for($other)->connected()->create();

        Message::factory()->create([
            'whatsapp_session_id' => $instance->id,
            'body' => 'top secret customer message text',
        ]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertDontSee('top secret customer message text');
    }

    public function test_is_admin_cannot_be_set_via_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'masstest@example.com',
            'password' => 'irrelevant-hashed-value',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
    }
}
