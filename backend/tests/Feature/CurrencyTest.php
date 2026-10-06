<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prices in Rand on a .za domain, Rupees everywhere else (the default).
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const ZA = 'http://instamessage.co.za';

    public function test_the_currency_follows_the_domain(): void
    {
        $this->get('http://instamessage.co.za/');
        $this->assertSame(Currency::ZAR, Currency::current());

        $this->get('http://www.instamessage.co.za/');
        $this->assertSame(Currency::ZAR, Currency::current());

        $this->get('http://instamessage.in/');
        $this->assertSame(Currency::INR, Currency::current());

        $this->get('/');
        $this->assertSame(Currency::INR, Currency::current());
    }

    public function test_the_currency_can_be_forced(): void
    {
        config(['currency.force' => 'zar']);
        $this->get('/');

        $this->assertSame(Currency::ZAR, Currency::current());
    }

    public function test_the_landing_page_shows_rupees_by_default(): void
    {
        $this->get('/')->assertSee('₹749', false)->assertDontSee('<span class="fs-5">Contact us</span>', false);
    }

    public function test_the_south_african_site_shows_rand_prices_or_contact_us(): void
    {
        Plan::where('slug', 'starter')->update(['price_zar' => 199]);

        $this->get(self::ZA.'/')
            ->assertSee('R199', false)
            ->assertDontSee('₹749', false)
            ->assertSee('<span class="fs-5">Contact us</span>', false) // growth / business have no Rand price yet
            ->assertSee('"priceCurrency":"ZAR"', false);

        $this->assertSame(0, Plan::localPrice(Plan::where('slug', 'free')->sole(), Currency::ZAR));
        $this->assertNull(Plan::localPrice(Plan::where('slug', 'growth')->sole(), Currency::ZAR));
        $this->assertSame(749, Plan::localPrice(Plan::where('slug', 'starter')->sole(), Currency::INR));
    }

    public function test_billing_on_the_south_african_site_has_no_online_checkout(): void
    {
        Http::fake();
        Plan::where('slug', 'starter')->update(['price_zar' => 199]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(self::ZA.'/billing')
            ->assertSee('R199', false)
            ->assertSee('Contact us to subscribe');

        $this->actingAs($user)->post(self::ZA.'/billing/subscribe/starter', ['phone' => '27821234567'])
            ->assertSessionHasErrors('plan');

        Http::assertNothingSent();
        $this->assertSame('free', $user->subscription->fresh()->plan);
    }

    public function test_admins_see_the_rand_price_first_on_the_south_african_site(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $starter = Plan::where('slug', 'starter')->sole();
        $starter->update(['price_zar' => 249]);

        // .za: Rand is the main price, Rupees underneath; Growth has no Rand price.
        $this->actingAs($admin)->get(self::ZA.'/admin/plans')
            ->assertSeeInOrder(['R249<span>/mo</span>', 'India: &#8377;749/mo'], false)
            ->assertSee('Rand price not set');
        $this->actingAs($admin)->get(self::ZA."/admin/plans/{$starter->id}/edit")
            ->assertSee('R249<span>/mo</span>', false)
            ->assertSee('on this South African site');

        // Anywhere else: Rupees first, as before.
        $this->actingAs($admin)->get('http://localhost/admin/plans') // full URL: the test client would reuse the last (.za) domain
            ->assertSeeInOrder(['&#8377;749<span>/mo</span>', 'South Africa: R249/mo'], false)
            ->assertDontSee('Rand price not set');
    }

    public function test_admins_can_set_and_clear_a_rand_price(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('slug', 'starter')->sole();
        $form = fn (array $overrides) => array_merge([
            'name' => $plan->name, 'description' => $plan->description, 'price' => $plan->price,
            'instances' => $plan->instances, 'messages_per_month' => $plan->messages_per_month,
        ], $overrides);

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), $form(['price_zar' => 249]))->assertSessionHasNoErrors();
        $this->assertSame(249, $plan->fresh()->price_zar);
        $this->actingAs($admin)->get(route('admin.plans.index'))->assertSee('South Africa: R249/mo');

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), $form(['price_zar' => '']))->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->price_zar);
    }
}
