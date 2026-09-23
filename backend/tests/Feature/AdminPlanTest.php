<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPlanTest extends TestCase
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

    public function test_guest_cannot_view_plan_management(): void
    {
        $this->get('/admin/plans')->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_view_plan_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/plans')->assertNotFound();
    }

    public function test_admin_sees_the_four_seeded_plans(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/plans')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Starter')
            ->assertSee('Growth')
            ->assertSee('Business');
    }

    public function test_admin_can_create_a_new_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Enterprise',
            'description' => 'For the biggest senders.',
            'price' => 9999,
            'instances' => 50,
            'messages_per_month' => 500000,
            'popular' => '1',
        ])->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('slug', 'enterprise')->first();
        $this->assertNotNull($plan);
        $this->assertSame(9999, $plan->price);
        $this->assertTrue($plan->popular);
        $this->assertSame('enterprise_v1', $plan->cashfree_plan_id);
        $this->assertSame(1, $plan->price_version);
    }

    public function test_a_new_free_plan_gets_no_cashfree_plan_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Trial',
            'description' => 'A second free tier.',
            'price' => 0,
            'instances' => 1,
            'messages_per_month' => 10,
        ]);

        $this->assertNull(Plan::where('slug', 'trial')->value('cashfree_plan_id'));
    }

    public function test_creating_a_plan_with_a_duplicate_name_gets_a_unique_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Starter', // collides with the seeded "starter" slug
            'description' => 'A second starter-ish plan.',
            'price' => 500,
            'instances' => 1,
            'messages_per_month' => 500,
        ]);

        $this->assertNotNull(Plan::where('slug', 'starter-2')->first());
    }

    public function test_editing_a_plans_limits_without_changing_price_keeps_the_same_cashfree_plan_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('slug', 'starter')->first();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => $plan->price,
            'instances' => 5, // only the limit changes
            'messages_per_month' => $plan->messages_per_month,
        ])->assertRedirect(route('admin.plans.index'));

        $plan->refresh();
        $this->assertSame(5, $plan->instances);
        $this->assertSame('starter_monthly', $plan->cashfree_plan_id);
        $this->assertSame(1, $plan->price_version);
    }

    public function test_editing_a_plans_price_bumps_the_price_version_and_cashfree_plan_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('slug', 'starter')->first();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => 999, // was 749
            'instances' => $plan->instances,
            'messages_per_month' => $plan->messages_per_month,
        ]);

        $plan->refresh();
        $this->assertSame(999, $plan->price);
        $this->assertSame(2, $plan->price_version);
        $this->assertSame('starter_v2', $plan->cashfree_plan_id);
    }

    public function test_existing_subscribers_are_unaffected_by_a_price_edit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subscriber = User::factory()->create();
        $subscriber->subscription->update(['plan' => 'starter', 'status' => 'active']);
        $plan = Plan::where('slug', 'starter')->first();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => 999,
            'instances' => $plan->instances,
            'messages_per_month' => $plan->messages_per_month,
        ]);

        // Nothing about the subscriber's own row changes — they keep
        // referencing the same plan slug, unaffected by the price edit.
        $this->assertSame('starter', $subscriber->subscription->fresh()->plan);
    }

    public function test_admin_can_delete_an_unused_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('status');

        $this->assertNull($plan->fresh());
    }

    public function test_cannot_delete_a_plan_with_active_subscribers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subscriber = User::factory()->create();
        $subscriber->subscription->update(['plan' => 'growth']);
        $plan = Plan::where('slug', 'growth')->first();

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('error');

        $this->assertNotNull($plan->fresh());
    }

    public function test_free_plan_cannot_be_deleted_while_any_user_exists(): void
    {
        // Every new user is auto-subscribed to 'free' (User::booted()), so
        // this is really just the "referenced plan" rule above applied to
        // the one plan that's true for by default.
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('slug', 'free')->first();

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))
            ->assertSessionHas('error');

        $this->assertNotNull($plan->fresh());
    }
}
