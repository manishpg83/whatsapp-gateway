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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/docs')->assertRedirect(route('login'));
    }

    public function test_logged_in_user_can_view_the_api_docs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/docs')
            ->assertOk()
            ->assertSee('/api/v1/messages/send');
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
