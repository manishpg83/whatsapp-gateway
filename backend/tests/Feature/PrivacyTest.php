<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrivacyTest extends TestCase
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

    public function test_guest_can_view_the_privacy_page(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Last updated: September 24, 2026')
            ->assertSee('BriskBrain Technologies')
            ->assertSee('briskbraintechnologies@gmail.com');
    }

    public function test_logged_in_user_can_view_the_privacy_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy');
    }

    public function test_every_table_of_contents_link_has_a_matching_section(): void
    {
        $html = $this->get('/privacy')->assertOk()->getContent();

        preg_match_all('/href="#([a-z-]+)"/', $html, $links);
        $this->assertNotEmpty($links[1]);

        foreach (array_unique($links[1]) as $id) {
            $this->assertStringContainsString("id=\"{$id}\"", $html, "Missing section #{$id}");
        }
    }

    public function test_register_page_footer_and_terms_link_to_the_privacy_page(): void
    {
        $this->get('/register')->assertSee(route('privacy'), escape: false);
        $this->get('/terms')->assertSee(route('privacy'), escape: false);
        $this->get('/')->assertSee(route('privacy'), escape: false); // footer
    }
}
