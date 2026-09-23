<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TermsTest extends TestCase
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

    public function test_guest_can_view_the_terms_page(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('not affiliated with', escape: false);
    }

    public function test_logged_in_user_can_view_the_terms_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/terms')
            ->assertOk()
            ->assertSee('Terms of Service');
    }

    public function test_register_page_links_to_the_terms_page(): void
    {
        $this->get('/register')->assertSee(route('terms'), escape: false);
    }
}
