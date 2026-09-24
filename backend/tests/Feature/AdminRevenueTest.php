<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRevenueTest extends TestCase
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

    private function customer(string $plan, string $status, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->subscription->update(['plan' => $plan, 'status' => $status]);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.revenue.index'))->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_view_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.revenue.index'))
            ->assertNotFound(); // EnsureUserIsAdmin hides the admin area entirely
    }

    public function test_summary_figures(): void
    {
        $admin = $this->admin();                     // free — ignored everywhere
        $this->customer('growth', 'active');         // ₹1,499
        $this->customer('starter', 'active');        // ₹749
        $this->customer('business', 'pending');      // at risk
        $this->customer('growth', 'past_due');       // at risk
        $this->customer('starter', 'cancelled');     // churned
        User::factory()->create();                   // another free user

        $this->actingAs($admin)->get(route('admin.revenue.index'))
            ->assertOk()
            ->assertViewHas('mrr', 1499 + 749)
            ->assertViewHas('arr', (1499 + 749) * 12)
            ->assertViewHas('statusCounts', [
                'active' => 2,
                'pending' => 1,
                'past_due' => 1,
                'cancelled' => 1,
            ])
            ->assertSee('₹2,248')
            ->assertDontSee('ARR'); // hidden from the page for now
    }

    public function test_revenue_by_plan_lists_only_paid_plans(): void
    {
        $admin = $this->admin();
        $this->customer('starter', 'active');
        $this->customer('starter', 'active');
        $this->customer('starter', 'pending');

        $response = $this->actingAs($admin)->get(route('admin.revenue.index'))->assertOk();
        $plans = $response->viewData('plans');

        $this->assertNull($plans->firstWhere('slug', 'free'));
        $starter = $plans->firstWhere('slug', 'starter');
        $this->assertSame(2, $starter->active_subscriptions_count);
        $this->assertSame(749 * 2, $starter->monthly_revenue);
    }

    public function test_subscriptions_table_lists_paid_customers_not_free_ones(): void
    {
        $admin = $this->admin();
        $payer = $this->customer('growth', 'active', ['name' => 'Paying Pat', 'email' => 'pat@example.com']);
        $payer->subscription->update(['cashfree_subscription_id' => 'sub_test_123', 'current_period_end' => '2026-10-24 00:00:00']);
        User::factory()->create(['name' => 'Free Fran']);

        $this->actingAs($admin)->get(route('admin.revenue.index'))
            ->assertOk()
            ->assertSee('Paying Pat')
            ->assertSee('pat@example.com')
            ->assertSee(route('admin.users.show', $payer), escape: false)
            ->assertSee('sub_test_123')
            ->assertSee('2026-10-24')
            ->assertDontSee('Free Fran');
    }

    public function test_can_filter_by_status(): void
    {
        $admin = $this->admin();
        $this->customer('growth', 'active', ['name' => 'Active Alice']);
        $this->customer('growth', 'past_due', ['name' => 'Late Larry']);

        $this->actingAs($admin)->get(route('admin.revenue.index', ['status' => 'past_due']))
            ->assertOk()
            ->assertSee('Late Larry')
            ->assertDontSee('Active Alice');
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        $admin = $this->admin();
        $this->customer('growth', 'active', ['name' => 'Active Alice']);

        $this->actingAs($admin)->get(route('admin.revenue.index', ['status' => 'bogus']))
            ->assertOk()
            ->assertSee('Active Alice');
    }

    public function test_can_search_by_name_or_email_without_leaking_free_users(): void
    {
        $admin = $this->admin();
        $this->customer('growth', 'active', ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->customer('starter', 'active', ['name' => 'Bob Smith', 'email' => 'bob@example.com']);
        User::factory()->create(['name' => 'Jane Free', 'email' => 'jane-free@example.com']);

        $this->actingAs($admin)->get(route('admin.revenue.index', ['search' => 'jane']))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertDontSee('Bob Smith')
            ->assertDontSee('Jane Free');

        $this->actingAs($admin)->get(route('admin.revenue.index', ['search' => 'bob@example']))
            ->assertOk()
            ->assertSee('Bob Smith')
            ->assertDontSee('Jane Doe');
    }

    public function test_admin_sidebar_and_dashboard_link_to_revenue(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.revenue.index'), escape: false)
            ->assertSee('View revenue');
    }
}
