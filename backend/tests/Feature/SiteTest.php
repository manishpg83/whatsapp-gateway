<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Indian site (default) and the South African one (.za) share every
 * page; only country details, currency and the hreflang links differ.
 * Full URLs everywhere: the test client reuses the previous request's domain.
 */
class SiteTest extends TestCase
{
    use RefreshDatabase;

    private const IN = 'http://instamessage.in';

    private const ZA = 'http://instamessage.co.za';

    public function test_the_indian_site_keeps_its_country_details(): void
    {
        $this->get(self::IN.'/')
            ->assertSee('<meta property="og:locale" content="en_IN">', false)
            ->assertSee('"areaServed":"IN"', false)
            ->assertSee('"inLanguage":"en-IN"', false)
            ->assertSee('919876543210')
            ->assertDontSee('27821234567');
    }

    public function test_the_south_african_site_uses_its_own_country_details(): void
    {
        $this->get(self::ZA.'/')
            ->assertSee('<meta property="og:locale" content="en_ZA">', false)
            ->assertSee('"areaServed":"ZA"', false)
            ->assertSee('"inLanguage":"en-ZA"', false)
            ->assertSee('27821234567')
            ->assertDontSee('919876543210');
    }

    public function test_both_sites_get_the_new_title_description_and_alt_text(): void
    {
        foreach ([self::IN, self::ZA] as $site) {
            $this->get($site.'/')
                ->assertSee('<title>WhatsApp REST API Without Business Verification - InstaMessage</title>', false)
                ->assertSee('<meta name="description" content="Connect your WhatsApp number by QR code and send messages, media &amp; webhooks via REST API. Free plan, no Meta approval. Live in minutes.">', false)
                ->assertSee('alt="InstaMessage logo"', false)
                ->assertSee('"@type":"FAQPage"', false);
        }
    }

    public function test_public_pages_link_to_the_same_page_on_the_other_site(): void
    {
        foreach ([self::IN, self::ZA, 'http://www.instamessage.co.za'] as $site) {
            $this->get($site.'/terms')
                ->assertSee('<link rel="alternate" hreflang="en-IN" href="https://instamessage.in/terms">', false)
                ->assertSee('<link rel="alternate" hreflang="en-ZA" href="https://instamessage.co.za/terms">', false)
                ->assertSee('<link rel="alternate" hreflang="x-default" href="https://instamessage.in/terms">', false);
        }

        $this->get(self::ZA.'/')->assertSee('hreflang="en-IN" href="https://instamessage.in"', false);
    }

    public function test_no_hreflang_links_on_private_pages_other_domains_or_when_turned_off(): void
    {
        $this->get(self::ZA.'/forgot-password')->assertDontSee('hreflang', false);
        $this->get('http://localhost/terms')->assertDontSee('hreflang', false);

        config(['sites.india_domain' => '']);
        $this->get(self::ZA.'/terms')->assertDontSee('hreflang', false);
    }

    public function test_examples_inside_the_app_follow_the_site(): void
    {
        $user = User::factory()->create();
        WhatsappSession::factory()->for($user)->create();

        $this->actingAs($user)->get(self::ZA.'/docs')->assertSee('27821234567')->assertDontSee('919876543210');
        $this->actingAs($user)->get(self::IN.'/docs')->assertSee('919876543210')->assertDontSee('27821234567');

        $this->actingAs($user)->get(self::ZA.'/bulk/sample.csv')->assertSee("27821234567,Thabo\n27831234567,Lerato", false);
        $this->actingAs($user)->get(self::IN.'/bulk/sample.csv')->assertSee("919876543210,Rahul\n919812345678,Priya", false);
    }
}
