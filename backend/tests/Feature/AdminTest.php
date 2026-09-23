<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            ->assertDontSee(route('admin.dashboard'), escape: false);
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
            ->assertSee(route('admin.dashboard'), escape: false);
    }

    public function test_admins_sidebar_is_the_admin_nav_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Admin accounts are platform-management accounts — the sidebar
        // shows Dashboard/Users/Billing (all admin.* routes) and never the
        // customer-only Instances/personal Billing/API Docs links.
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.dashboard'), escape: false)
            ->assertSee(route('admin.users.index'), escape: false)
            ->assertSee(route('admin.plans.index'), escape: false)
            ->assertDontSee(route('instances.index'), escape: false)
            ->assertDontSee(route('docs.index'), escape: false);
    }

    public function test_regular_users_sidebar_is_unaffected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('instances.index'), escape: false)
            ->assertSee(route('billing.index'), escape: false)
            ->assertSee(route('docs.index'), escape: false)
            ->assertDontSee(route('admin.users.index'), escape: false);
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

    public function test_admin_user_detail_page_shows_total_sent_and_received_across_instances(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $instanceA = WhatsappSession::factory()->for($other)->connected()->create();
        $instanceB = WhatsappSession::factory()->for($other)->connected()->create();
        Message::factory()->for($instanceA, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'sent']);
        Message::factory()->for($instanceB, 'whatsappSession')->create(['direction' => 'outgoing', 'status' => 'sent']);
        Message::factory()->for($instanceA, 'whatsappSession')->create(['direction' => 'incoming']);

        $this->actingAs($admin)->get(route('admin.users.show', $other))
            ->assertOk()
            ->assertSee('2 sent') // top stat card
            ->assertSee('1 received') // top stat card
            ->assertSeeInOrder(['Total', '2', '0', '1']); // the instances table's footer row
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

    public function test_admin_dashboard_shows_platform_wide_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payingUser = User::factory()->create();
        $payingUser->subscription->update(['plan' => 'growth']);
        WhatsappSession::factory()->for($payingUser)->connected()->create();
        WhatsappSession::factory()->for($payingUser)->create(); // not connected

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalUsers', 2)
            ->assertViewHas('paidUsers', 1)
            ->assertViewHas('totalInstances', 2)
            ->assertViewHas('connectedInstances', 1)
            ->assertSee('Growth'); // plan-breakdown table
    }

    public function test_admin_can_change_a_users_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.plan.update', $user), ['plan' => 'growth'])
            ->assertRedirect(route('admin.users.show', $user));

        $this->assertSame('growth', $user->subscription->fresh()->plan);
    }

    public function test_changing_plan_to_an_unknown_slug_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.plan.update', $user), ['plan' => 'does-not-exist'])
            ->assertSessionHasErrors('plan');

        $this->assertSame('free', $user->subscription->fresh()->plan);
    }

    public function test_admin_can_suspend_and_unsuspend_a_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.suspend', $user))
            ->assertRedirect(route('admin.users.show', $user));
        $this->assertTrue($user->fresh()->is_suspended);

        $this->actingAs($admin)->post(route('admin.users.unsuspend', $user))
            ->assertRedirect(route('admin.users.show', $user));
        $this->assertFalse($user->fresh()->is_suspended);
    }

    public function test_a_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'my-password-123', 'is_suspended' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'my-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.users.suspend', $admin))
            ->assertRedirect(route('admin.users.show', $admin))
            ->assertSessionHas('error');

        $this->assertFalse($admin->fresh()->is_suspended);
    }

    public function test_admin_cannot_suspend_another_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.users.suspend', $otherAdmin))
            ->assertRedirect(route('admin.users.show', $otherAdmin))
            ->assertSessionHas('error');

        $this->assertFalse($otherAdmin->fresh()->is_suspended);
    }

    public function test_admin_can_delete_a_user(): void
    {
        Http::fake(['*' => Http::response(['stopped' => true], 200)]);

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($user->fresh());
        $this->assertNull(WhatsappSession::find($instance->id));
        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:3001/sessions/{$instance->instance_id}");
    }

    public function test_admin_deleting_a_user_proceeds_even_if_the_worker_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($user->fresh());
    }

    public function test_admin_cannot_delete_a_user_with_an_active_paid_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $user->subscription->update(['plan' => 'starter', 'status' => 'active']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('error');

        $this->assertNotNull($user->fresh());
    }

    public function test_admin_cannot_delete_their_own_account_from_the_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.show', $admin))
            ->assertSessionHas('error');

        $this->assertNotNull($admin->fresh());
    }

    public function test_admin_cannot_delete_another_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $otherAdmin))
            ->assertRedirect(route('admin.users.show', $otherAdmin))
            ->assertSessionHas('error');

        $this->assertNotNull($otherAdmin->fresh());
    }
}
