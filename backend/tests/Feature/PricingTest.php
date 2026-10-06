<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public /pricing page. Full URLs: the test client reuses the previous
 * request's domain otherwise.
 */
class PricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_search_engines_can_see_the_pricing_page(): void
    {
        $this->get('http://localhost/pricing')
            ->assertOk()
            ->assertSee('<title>WhatsApp API Pricing - InstaMessage</title>', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<h1 class="lp-h2">WhatsApp API pricing</h1>', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('Create your free account');

        $this->get('http://localhost/sitemap.xml')->assertSee('<loc>http://localhost/pricing</loc>', false);
    }

    public function test_the_comparison_table_lists_every_plan_and_its_limits(): void
    {
        $this->get('http://localhost/pricing')
            ->assertSeeInOrder(['Feature', 'Free', 'Starter', 'Growth', 'Business'])
            ->assertSeeInOrder(['Messages sent per month', '50', '1,000', '5,000', '50,000'])
            ->assertSeeInOrder(['Chatbot entries', '5', '50', '200', '1,000'])
            ->assertSee('Unlimited')
            ->assertSee('includes 50 messages a month', false);
    }

    public function test_indian_prices_and_payment_text_by_default(): void
    {
        $this->get('http://localhost/pricing')
            ->assertSee('₹749', false)
            ->assertSee('Prices in Indian Rupees (INR), billed monthly through Cashfree.')
            ->assertDontSee('Prices in South African Rand');
    }

    public function test_rand_prices_and_contact_us_on_the_south_african_site(): void
    {
        Plan::where('slug', 'starter')->update(['price_zar' => 199]);

        $this->get('http://instamessage.co.za/pricing')
            ->assertSee('R199', false)
            ->assertDontSee('₹749', false)
            ->assertSee('Prices in South African Rand (ZAR).')
            ->assertSee('Online payment for South Africa is coming soon.')
            ->assertDontSee('billed monthly through Cashfree');
    }

    public function test_the_menu_links_to_the_pricing_page_and_home_links_to_the_comparison(): void
    {
        $this->get('http://localhost/')
            ->assertSee('href="http://localhost/pricing"', false)
            ->assertSee('Compare all plan features');

        // From /pricing, the other menu links lead back to the home page sections.
        $this->get('http://localhost/pricing')
            ->assertSee('href="http://localhost#features"', false);
    }

    public function test_logged_in_users_are_sent_to_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('http://localhost/pricing')
            ->assertSee('Go to your dashboard')
            ->assertDontSee('Create your free account');
    }
}
