<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiDocsTest extends TestCase
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

    public function test_guests_and_search_engines_can_read_the_docs(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('/api/v1/messages/send')
            ->assertSee('<title>WhatsApp API Documentation - InstaMessage</title>', escape: false)
            ->assertSee('<meta name="robots" content="index, follow">', escape: false)
            ->assertSee('Create a free account')
            ->assertDontSee('Get your token');

        $this->get('/sitemap.xml')->assertSee('<loc>'.route('docs.index').'</loc>', escape: false);
        $this->get('/')->assertSee('href="'.route('docs.index').'"', escape: false);
    }

    public function test_logged_in_user_can_view_the_api_docs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/docs')
            ->assertOk()
            ->assertSee('/api/v1/messages/send')
            ->assertSee('Get your token');
    }

    public function test_docs_page_includes_code_samples_in_every_language(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/docs')
            ->assertOk()
            ->assertSee('curl')
            ->assertSee('JavaScript')
            ->assertSee('PHP')
            ->assertSee('Python')
            ->assertSee('.NET (C#)', escape: false)
            ->assertSee('Java')
            ->assertSee('import requests', escape: false)
            ->assertSee('HttpClient client = HttpClient.newHttpClient();', escape: false);
    }
}
