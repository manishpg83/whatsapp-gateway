<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminInstanceTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // Sets updated_at directly — a normal update() would bump it to now.
    private function lastUpdatedMinutesAgo(WhatsappSession $instance, int $minutes): void
    {
        DB::table('whatsapp_sessions')->where('id', $instance->id)->update(['updated_at' => now()->subMinutes($minutes)]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.instances.index'))->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_view_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.instances.index'))
            ->assertNotFound(); // EnsureUserIsAdmin hides the admin area entirely
    }

    public function test_admin_sees_every_users_instances_with_their_owner(): void
    {
        $jane = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Smith']);
        WhatsappSession::factory()->for($jane)->connected()->create(['name' => 'Jane shop', 'phone_number' => '919000000001']);
        WhatsappSession::factory()->for($bob)->create(['name' => 'Bob office', 'status' => 'logged_out']);

        $this->actingAs($this->admin())->get(route('admin.instances.index'))
            ->assertOk()
            ->assertSee('Jane shop')
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com')
            ->assertSee('919000000001')
            ->assertSee(route('admin.users.show', $jane), escape: false)
            ->assertSee('Bob office')
            ->assertSee('Bob Smith');
    }

    public function test_counters_show_each_status(): void
    {
        WhatsappSession::factory()->count(2)->connected()->create();
        WhatsappSession::factory()->create(['status' => 'qr_pending']); // waiting
        $stuck = WhatsappSession::factory()->create(['status' => 'connecting']);
        $this->lastUpdatedMinutesAgo($stuck, 30);
        WhatsappSession::factory()->create(['status' => 'disconnected']);

        $this->actingAs($this->admin())->get(route('admin.instances.index'))
            ->assertOk()
            ->assertViewHas('counts', [
                'connected' => 2,
                'waiting' => 1,
                'stuck' => 1,
                'disconnected' => 1,
                'logged_out' => 0,
            ]);
    }

    public function test_waiting_instance_becomes_stuck_after_ten_silent_minutes(): void
    {
        $instance = WhatsappSession::factory()->create(['name' => 'Silent one', 'status' => 'qr_pending']);

        $this->lastUpdatedMinutesAgo($instance, 9);
        $this->assertFalse($instance->fresh()->isStuck());

        $this->lastUpdatedMinutesAgo($instance, 11);
        $this->assertTrue($instance->fresh()->isStuck());

        $this->actingAs($this->admin())->get(route('admin.instances.index'))
            ->assertOk()
            ->assertSeeInOrder(['Silent one', 'Stuck']);
    }

    public function test_a_connected_instance_is_never_stuck_however_old(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();
        $this->lastUpdatedMinutesAgo($instance, 60 * 24);

        $this->assertFalse($instance->fresh()->isStuck());
    }

    public function test_can_filter_by_status(): void
    {
        WhatsappSession::factory()->connected()->create(['name' => 'Online instance']);
        WhatsappSession::factory()->create(['name' => 'Offline instance', 'status' => 'disconnected']);

        $this->actingAs($this->admin())->get(route('admin.instances.index', ['status' => 'disconnected']))
            ->assertOk()
            ->assertSee('Offline instance')
            ->assertDontSee('Online instance');
    }

    public function test_can_filter_to_stuck_only(): void
    {
        WhatsappSession::factory()->create(['name' => 'Fresh waiting', 'status' => 'qr_pending']);
        $stuck = WhatsappSession::factory()->create(['name' => 'Long waiting', 'status' => 'connecting']);
        $this->lastUpdatedMinutesAgo($stuck, 30);

        $this->actingAs($this->admin())->get(route('admin.instances.index', ['status' => 'stuck']))
            ->assertOk()
            ->assertSee('Long waiting')
            ->assertDontSee('Fresh waiting');

        $this->actingAs($this->admin())->get(route('admin.instances.index', ['status' => 'waiting']))
            ->assertOk()
            ->assertSee('Fresh waiting')
            ->assertDontSee('Long waiting');
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        WhatsappSession::factory()->connected()->create(['name' => 'Still listed']);

        $this->actingAs($this->admin())->get(route('admin.instances.index', ['status' => 'bogus']))
            ->assertOk()
            ->assertSee('Still listed');
    }

    public function test_can_search_by_instance_name_owner_name_or_email(): void
    {
        $jane = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@example.com']);
        WhatsappSession::factory()->for($jane)->create(['name' => 'Bakery phone']);
        WhatsappSession::factory()->for($bob)->create(['name' => 'Garage phone']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.instances.index', ['search' => 'bakery']))
            ->assertSee('Bakery phone')->assertDontSee('Garage phone');

        $this->actingAs($admin)->get(route('admin.instances.index', ['search' => 'Bob']))
            ->assertSee('Garage phone')->assertDontSee('Bakery phone');

        $this->actingAs($admin)->get(route('admin.instances.index', ['search' => 'jane@example']))
            ->assertSee('Bakery phone')->assertDontSee('Garage phone');
    }

    public function test_never_shows_tokens_webhook_secrets_or_message_text(): void
    {
        $instance = WhatsappSession::factory()->connected()->create([
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'super-secret-webhook-key',
        ]);
        ['plainText' => $plainText, 'token' => $token] = ApiToken::generateFor($instance, 'x');
        $instance->messages()->create([
            'direction' => 'outgoing', 'to_number' => '919999999999',
            'body' => 'Private message text', 'status' => 'sent',
        ]);

        $this->actingAs($this->admin())->get(route('admin.instances.index'))
            ->assertOk()
            ->assertDontSee('super-secret-webhook-key')
            ->assertDontSee($plainText, escape: false)
            ->assertDontSee($token->token_hash, escape: false)
            ->assertDontSee('Private message text');
    }

    public function test_admin_sidebar_has_the_instances_link(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.instances.index'), escape: false);
    }
}
