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

    public function test_logged_in_user_can_view_the_landing_page_with_a_dashboard_button(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Go to your dashboard')
            ->assertSee(route('dashboard'), escape: false)
            ->assertDontSee('Create your free account');
    }

    public function test_logged_in_admins_dashboard_button_goes_to_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/')
            ->assertOk()
            ->assertSee('href="'.route('admin.dashboard').'"', escape: false)
            ->assertDontSee('href="'.route('dashboard').'"', escape: false);
    }

    public function test_app_logo_and_menu_link_to_the_landing_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('home').'"', escape: false)
            ->assertSee('View website');
    }

    public function test_admin_can_still_open_the_user_dashboard_by_url(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/dashboard')
            ->assertOk();
    }
}
