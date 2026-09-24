<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
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
        return User::factory()->create(['is_admin' => true, 'name' => 'Admin Alex']);
    }

    private function planForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Enterprise',
            'description' => 'For the biggest senders.',
            'price' => 9999,
            'instances' => 50,
            'messages_per_month' => 500000,
        ], $overrides);
    }

    // --- Each action is recorded ---------------------------------------

    public function test_changing_a_users_plan_is_logged_with_from_and_to(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($admin)->put(route('admin.users.plan.update', $user), ['plan' => 'growth']);

        $log = AdminAuditLog::sole();
        $this->assertSame('user.plan_changed', $log->action);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('Admin Alex', $log->admin_name);
        $this->assertSame('user', $log->target_type);
        $this->assertSame($user->id, $log->target_id);
        $this->assertSame('Jane Doe (jane@example.com)', $log->target_label);
        $this->assertSame(['plan' => ['from' => 'free', 'to' => 'growth']], $log->details);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame(['plan: free → growth'], $log->detailLines());
    }

    public function test_suspending_and_unsuspending_are_logged(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.suspend', $user));
        $this->actingAs($admin)->post(route('admin.users.unsuspend', $user));

        $this->assertSame(
            ['user.suspended', 'user.unsuspended'],
            AdminAuditLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_deleting_a_user_is_logged_and_the_entry_outlives_the_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Gone Gary', 'email' => 'gary@example.com']);
        $userId = $user->id;

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $this->assertNull(User::find($userId));
        $log = AdminAuditLog::sole();
        $this->assertSame('user.deleted', $log->action);
        $this->assertSame($userId, $log->target_id);
        $this->assertSame('Gone Gary (gary@example.com)', $log->target_label);

        // Still readable on the page, as plain text (no link to a missing user).
        $this->actingAs($admin)->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertSee('Gone Gary (gary@example.com)')
            ->assertDontSee(route('admin.users.show', $userId), escape: false);
    }

    public function test_creating_a_plan_is_logged(): void
    {
        $this->actingAs($this->admin())->post(route('admin.plans.store'), $this->planForm());

        $log = AdminAuditLog::sole();
        $this->assertSame('plan.created', $log->action);
        $this->assertSame('plan', $log->target_type);
        $this->assertSame('Enterprise', $log->target_label);
        $this->assertSame(9999, $log->details['price']);
    }

    public function test_updating_a_plan_logs_only_the_fields_that_changed(): void
    {
        $admin = $this->admin();
        $starter = Plan::where('slug', 'starter')->sole();

        $this->actingAs($admin)->put(route('admin.plans.update', $starter), [
            'name' => $starter->name,
            'description' => $starter->description,
            'price' => 899,                                   // changed
            'instances' => $starter->instances,
            'messages_per_month' => $starter->messages_per_month,
            'popular' => $starter->popular ? '1' : '0',
        ]);

        $log = AdminAuditLog::sole();
        $this->assertSame('plan.updated', $log->action);
        $this->assertSame(['price' => ['from' => 749, 'to' => 899]], $log->details);
    }

    public function test_saving_a_plan_without_changes_logs_nothing(): void
    {
        $starter = Plan::where('slug', 'starter')->sole();

        $this->actingAs($this->admin())->put(route('admin.plans.update', $starter), [
            'name' => $starter->name,
            'description' => $starter->description,
            'price' => $starter->price,
            'instances' => $starter->instances,
            'messages_per_month' => $starter->messages_per_month,
            'popular' => $starter->popular ? '1' : '0',
        ]);

        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_deleting_a_plan_is_logged(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.plans.store'), $this->planForm());
        $plan = Plan::where('slug', 'enterprise')->sole();

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $this->assertSame('plan.deleted', AdminAuditLog::latest('id')->first()->action);
    }

    // --- Blocked attempts change nothing, so aren't logged -------------

    public function test_blocked_actions_are_not_logged(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $growth = Plan::where('slug', 'growth')->sole();
        User::factory()->create()->subscription->update(['plan' => 'growth']); // plan in use

        $this->actingAs($admin)->post(route('admin.users.suspend', $admin));          // self
        $this->actingAs($admin)->post(route('admin.users.suspend', $otherAdmin));     // another admin
        $this->actingAs($admin)->delete(route('admin.users.destroy', $otherAdmin));   // another admin
        $this->actingAs($admin)->delete(route('admin.plans.destroy', $growth));       // has subscribers

        $this->assertSame(0, AdminAuditLog::count());
    }

    // --- The pages -------------------------------------------------------

    public function test_guest_and_regular_user_cannot_view_the_audit_log(): void
    {
        $this->get(route('admin.audit-log.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.audit-log.index'))
            ->assertNotFound(); // EnsureUserIsAdmin hides the admin area entirely
    }

    public function test_audit_log_page_lists_entries_and_links_existing_users(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->actingAs($admin)->post(route('admin.users.suspend', $user));

        $this->actingAs($admin)->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertSee('Admin Alex')
            ->assertSee('Suspended user')
            ->assertSee('Jane Doe (jane@example.com)')
            ->assertSee(route('admin.users.show', $user), escape: false)
            ->assertSee('127.0.0.1');
    }

    public function test_can_filter_by_action_and_search(): void
    {
        $admin = $this->admin();
        $jane = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@example.com']);
        $this->actingAs($admin)->post(route('admin.users.suspend', $jane));
        $this->actingAs($admin)->put(route('admin.users.plan.update', $bob), ['plan' => 'growth']);

        $this->actingAs($admin)->get(route('admin.audit-log.index', ['action' => 'user.suspended']))
            ->assertOk()
            ->assertSee('jane@example.com')
            ->assertDontSee('bob@example.com');

        $this->actingAs($admin)->get(route('admin.audit-log.index', ['search' => 'bob@']))
            ->assertOk()
            ->assertSee('bob@example.com')
            ->assertDontSee('jane@example.com');
    }

    public function test_user_admin_page_shows_only_that_users_entries(): void
    {
        $admin = $this->admin();
        $jane = User::factory()->create(['email' => 'jane@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);
        $this->actingAs($admin)->put(route('admin.users.plan.update', $jane), ['plan' => 'growth']);
        $this->actingAs($admin)->put(route('admin.users.plan.update', $bob), ['plan' => 'starter']);

        $this->actingAs($admin)->get(route('admin.users.show', $jane))
            ->assertOk()
            ->assertSee('Admin actions on this account')
            ->assertSee('plan: free → growth')
            ->assertDontSee('plan: free → starter');
    }

    public function test_there_are_no_routes_to_edit_or_delete_entries(): void
    {
        $auditRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit-log'));

        $this->assertSame([['GET', 'HEAD']], $auditRoutes->map(fn ($r) => $r->methods())->values()->all());
    }
}
