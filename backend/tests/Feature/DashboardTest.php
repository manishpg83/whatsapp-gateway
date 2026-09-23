<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_home_page_shows_the_landing_page_to_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Log in');
    }

    public function test_home_page_forwards_logged_in_users_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Jane Doe']))
            ->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee('Welcome,')
            ->assertSee('Jane Doe');
    }

    public function test_dashboard_shows_the_users_own_account_details(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome,')
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com')
            ->assertSee('Member since '.$user->created_at->format('M j, Y'));
    }

    public function test_dashboard_never_shows_another_users_details(): void
    {
        $me = User::factory()->create(['name' => 'Jane Doe']);
        User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com']);

        $this->actingAs($me)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Other Person')
            ->assertDontSee('other@example.com');
    }

    public function test_dashboard_shows_zero_instances_and_no_connection_yet(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Instances')
            ->assertSee('No instances yet');
    }

    public function test_dashboard_shows_real_per_user_instance_counts(): void
    {
        $me = User::factory()->create();
        WhatsappSession::factory()->for($me)->connected()->create();
        WhatsappSession::factory()->for($me)->create(['status' => 'connecting']);

        // Another user's instances must never affect my counts.
        $other = User::factory()->create();
        WhatsappSession::factory()->for($other)->connected()->create();

        $this->actingAs($me)->get('/dashboard')
            ->assertOk()
            ->assertSee('2') // instanceCount
            ->assertSee('1 of 2 connected');
    }

    public function test_dashboard_shows_total_sent_and_received_across_all_instances(): void
    {
        $me = User::factory()->create();
        $instanceA = WhatsappSession::factory()->for($me)->connected()->create();
        $instanceB = WhatsappSession::factory()->for($me)->connected()->create();
        Message::factory()->for($instanceA, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'sent']);
        Message::factory()->for($instanceB, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'sent']);
        Message::factory()->for($instanceA, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'failed']);
        Message::factory()->for($instanceB, 'whatsappSession')->create(['direction' => 'incoming']);

        // Another user's messages must never count toward my totals.
        $other = User::factory()->create();
        $otherInstance = WhatsappSession::factory()->for($other)->connected()->create();
        Message::factory()->for($otherInstance, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'sent']);

        $this->actingAs($me)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('sentCount', 2)
            ->assertViewHas('receivedCount', 1)
            ->assertSee('2 sent')
            ->assertSee('1 received');
    }

    public function test_navigation_links_are_shown_to_logged_in_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Instances')
            ->assertSee('API Docs')
            ->assertSee('Log out');
    }

    public function test_guest_pages_show_login_and_register_links_not_the_app_nav(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Register')
            ->assertDontSee('API Docs')
            ->assertDontSee('Log out');
    }
}
