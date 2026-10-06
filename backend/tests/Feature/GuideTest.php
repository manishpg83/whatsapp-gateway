<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public tutorials under /guides. Full URLs: the test client reuses the
 * previous request's domain otherwise.
 */
class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_guides_index_lists_every_guide(): void
    {
        $response = $this->get('http://localhost/guides')
            ->assertOk()
            ->assertSee('<title>WhatsApp API Guides &amp; Tutorials - InstaMessage</title>', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);

        foreach (Guides::all() as $slug => $guide) {
            $response->assertSee($guide['title'])->assertSee('href="http://localhost/guides/'.$slug.'"', false);
        }
    }

    public function test_every_guide_page_works_and_is_ready_for_search_engines(): void
    {
        foreach (Guides::all() as $slug => $guide) {
            $this->get("http://localhost/guides/{$slug}")
                ->assertOk()
                ->assertSee('<title>'.$guide['title'].' - InstaMessage</title>', false)
                ->assertSee('<h1 class="lp-h2 mt-2 mb-3">'.$guide['title'].'</h1>', false)
                ->assertSee('"@type":"TechArticle"', false)
                ->assertSee('"@type":"BreadcrumbList"', false)
                ->assertSee('Protect your WhatsApp number')
                ->assertSee('More guides');
        }
    }

    public function test_sending_guides_use_the_real_endpoint_and_credentials(): void
    {
        foreach (['php', 'python', 'nodejs'] as $language) {
            $this->get("http://localhost/guides/send-whatsapp-message-{$language}")
                ->assertSee('http://localhost/api/v1', false)
                ->assertSee('INSTAMESSAGE_TOKEN')
                ->assertSee('Receive incoming messages with a webhook');
        }
    }

    public function test_the_webhook_guide_matches_what_we_send(): void
    {
        $this->get('http://localhost/guides/receive-whatsapp-messages-webhook')
            ->assertOk()
            ->assertSee('X-Webhook-Signature')
            ->assertSee('INSTAMESSAGE_WEBHOOK_SECRET')
            ->assertSee('Send test webhook') // the real button name on the instance page
            ->assertSee('"event": "message.received"', false)
            ->assertSee('"event": "message.status"', false);
    }

    public function test_code_examples_follow_the_site(): void
    {
        $this->get('http://instamessage.co.za/guides/send-whatsapp-message-python')
            ->assertSee('http://instamessage.co.za/api/v1', false) // https on the live site
            ->assertSee('27821234567')
            ->assertDontSee('919876543210');

        $this->get('http://localhost/guides/send-whatsapp-message-python')
            ->assertSee('919876543210')
            ->assertDontSee('27821234567');
    }

    public function test_the_php_example_is_shown_as_code_not_run(): void
    {
        // "<?php" must reach the browser as text inside the code box.
        $this->get('http://localhost/guides/send-whatsapp-message-php')
            ->assertSee('&lt;?php', false)
            ->assertSee("curl_init('http://localhost/api/v1' . \$path)", false);
    }

    public function test_an_unknown_guide_is_a_404(): void
    {
        $this->get('http://localhost/guides/send-whatsapp-message-cobol')->assertNotFound();
    }

    public function test_guides_are_in_the_sitemap_and_footer(): void
    {
        $sitemap = $this->get('http://localhost/sitemap.xml')->assertSee('<loc>http://localhost/guides</loc>', false);

        foreach (array_keys(Guides::all()) as $slug) {
            $sitemap->assertSee("<loc>http://localhost/guides/{$slug}</loc>", false);
        }

        $this->get('http://localhost/')->assertSee('href="http://localhost/guides"', false);
    }
}
