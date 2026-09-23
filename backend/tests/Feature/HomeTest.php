<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeTest extends TestCase
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

    public function test_guest_sees_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Create your free account')
            ->assertSee('Growth')
            ->assertSee(route('register'), escape: false);
    }

    public function test_logged_in_user_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
