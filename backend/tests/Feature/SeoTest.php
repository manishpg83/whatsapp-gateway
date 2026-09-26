<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeoTest extends TestCase
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

    public function test_landing_page_has_meta_tags_social_preview_and_structured_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="description" content="Connect your own WhatsApp number', escape: false)
            ->assertSee('<meta name="robots" content="index, follow">', escape: false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', escape: false)
            ->assertSee('<meta property="og:image" content="'.asset('images/og-image.png').'">', escape: false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false)
            ->assertSee('"@type":"SoftwareApplication"', escape: false)
            ->assertSee('"priceCurrency":"INR"', escape: false);
    }

    public function test_text_is_escaped_exactly_once(): void
    {
        $this->get('/')
            ->assertSee('send &amp; receive', escape: false)
            ->assertDontSee('&amp;amp;', escape: false);
    }

    public function test_public_legal_and_contact_pages_are_indexable_with_breadcrumbs(): void
    {
        foreach (['terms', 'privacy', 'contact'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('<meta name="robots" content="index, follow">', escape: false)
                ->assertSee('"@type":"BreadcrumbList"', escape: false);
        }
    }

    public function test_canonical_url_drops_the_query_string(): void
    {
        $this->get(route('contact', ['topic' => 'privacy']))
            ->assertSee('<link rel="canonical" href="'.route('contact').'">', escape: false);
    }

    public function test_private_pages_are_noindex(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.dashboard'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);
    }

    public function test_auth_pages_login_and_register_are_indexable_but_password_reset_is_not(): void
    {
        $this->get(route('login'))->assertSee('<meta name="robots" content="index, follow">', escape: false);
        $this->get(route('register'))->assertSee('<meta name="robots" content="index, follow">', escape: false);
        $this->get(route('password.request'))->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_sitemap_lists_only_public_pages(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('home').'</loc>', escape: false)
            ->assertSee('<loc>'.route('terms').'</loc>', escape: false)
            ->assertSee('<loc>'.route('privacy').'</loc>', escape: false)
            ->assertSee('<loc>'.route('contact').'</loc>', escape: false)
            ->assertDontSee('/dashboard')
            ->assertDontSee('/admin');
    }
}
