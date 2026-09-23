<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingTest extends TestCase
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

    public function test_guest_cannot_view_billing(): void
    {
        $this->get('/billing')->assertRedirect(route('login'));
    }

    public function test_new_user_starts_on_the_free_plan(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->subscription);
        $this->assertSame('free', $user->subscription->plan);
        $this->assertSame('active', $user->subscription->status);
    }

    public function test_billing_page_renders_and_shows_the_current_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/billing')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Starter')
            ->assertSee('Growth')
            ->assertSee('Business');
    }

    public function test_subscribing_to_a_paid_plan_creates_a_pending_subscription_and_shows_checkout(): void
    {
        Http::fake(['*' => Http::response([
            'subscription_id' => 'sub_test',
            'cf_subscription_id' => '1',
            'subscription_session_id' => 'subs_session_abc123',
            'subscription_status' => 'INITIALIZED',
        ], 200)]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('billing.subscribe', 'starter'), [
            'phone' => '919999999999',
        ]);

        $response->assertOk()->assertSee('subs_session_abc123', escape: false);

        $subscription = $user->subscription->fresh();
        $this->assertSame('starter', $subscription->plan);
        $this->assertSame('pending', $subscription->status);
        $this->assertNotNull($subscription->cashfree_subscription_id);

        // ensurePlanExists() posts to /pg/plans first, then the actual
        // subscription to /pg/subscriptions — both carry the auth headers.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pg/plans')
            && $request->hasHeader('x-client-secret')
            && $request['plan_recurring_amount'] === 749);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/pg/subscriptions')
            && $request->hasHeader('x-client-secret')
            && $request['plan_details']['plan_id'] === 'starter_monthly');
    }

    public function test_subscribing_requires_a_phone_number(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.subscribe', 'starter'), [])
            ->assertSessionHasErrors('phone');

        $this->assertSame('free', $user->subscription->fresh()->plan);
    }

    public function test_cannot_subscribe_to_the_free_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.subscribe', 'free'), ['phone' => '919999999999'])
            ->assertNotFound();
    }

    public function test_cannot_subscribe_to_an_unknown_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.subscribe', 'enterprise'), ['phone' => '919999999999'])
            ->assertNotFound();
    }

    public function test_shows_a_friendly_error_when_cashfree_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.subscribe', 'starter'), ['phone' => '919999999999'])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('error');

        // Still on the free plan — nothing half-applied.
        $this->assertSame('free', $user->subscription->fresh()->plan);
    }

    public function test_cancelling_a_paid_subscription_reverts_to_the_free_plan(): void
    {
        Http::fake(['*' => Http::response(['subscription_status' => 'CANCELLED'], 200)]);

        $user = User::factory()->create();
        $user->subscription()->update([
            'plan' => 'growth',
            'status' => 'active',
            'cashfree_subscription_id' => 'sub_test_123',
        ]);

        $this->actingAs($user)->post(route('billing.cancel'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('status');

        $subscription = $user->subscription->fresh();
        $this->assertSame('free', $subscription->plan);
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->cashfree_subscription_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/pg/subscriptions/sub_test_123/manage')
            && $request['action'] === 'CANCEL');
    }

    public function test_cannot_cancel_when_already_on_the_free_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.cancel'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('error');

        $this->assertSame('free', $user->subscription->fresh()->plan);
    }

    public function test_shows_a_friendly_error_when_cashfree_cancellation_fails(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $user = User::factory()->create();
        $user->subscription()->update([
            'plan' => 'growth',
            'status' => 'active',
            'cashfree_subscription_id' => 'sub_test_123',
        ]);

        $this->actingAs($user)->post(route('billing.cancel'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('error');

        // Still on the paid plan — the failed cancel attempt changed nothing.
        $this->assertSame('growth', $user->subscription->fresh()->plan);
    }

    public function test_user_cannot_have_two_subscription_rows(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    public function test_billing_return_accepts_a_post_and_bounces_to_the_billing_page(): void
    {
        // Cashfree's checkout posts the browser back here (not a GET), so
        // this route — unlike everywhere else — must accept POST and skip
        // CSRF (no token of ours was ever issued to Cashfree's page).
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.return'))
            ->assertRedirect(route('billing.index'));
    }

    public function test_billing_return_works_even_when_logged_out(): void
    {
        $this->post(route('billing.return'))
            ->assertRedirect(route('billing.index'));
    }
}
