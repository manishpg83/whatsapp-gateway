<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCompanySettingsTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Admin Alex']);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme Labs',
            'email' => 'hello@acme.test',
            'phone' => '+27 21 555 0100',
            'website' => 'https://acme.test/',
            'street' => '12 Long Street',
            'city' => 'Cape Town',
            'region' => 'Western Cape',
            'postal_code' => '8001',
            'country' => 'South Africa',
            'country_code' => 'za',
        ], $overrides);
    }

    public function test_only_admins_can_open_it(): void
    {
        $this->get('/admin/company')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/company')->assertNotFound();

        $this->actingAs($this->admin())->get('/admin/company')->assertOk()
            ->assertSee('Company settings')
            ->assertSee('value="BriskBrain Technologies"', false)
            ->assertSee('Using the built-in details');
    }

    public function test_saved_details_are_shown_on_the_public_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/company', $this->form())
            ->assertRedirect('/admin/company')
            ->assertSessionHas('status', 'Company details saved. The About, Privacy and Terms pages now show them.');

        $saved = Setting::where('key', 'company')->sole();
        $this->assertSame('+27215550100', $saved->value['phone_e164']);
        $this->assertSame('ZA', $saved->value['address']['country_code']);

        $this->get('/about')->assertOk()
            ->assertSee('Acme Labs')
            ->assertSee('12 Long Street')
            ->assertSee('Cape Town, Western Cape 8001')
            ->assertSee('href="tel:+27215550100"', false)
            ->assertSee('href="mailto:hello@acme.test"', false)
            ->assertSee('"addressCountry":"ZA"', false)
            ->assertDontSee('BriskBrain');

        $this->get('/privacy')->assertSee('href="mailto:hello@acme.test"', false)->assertSee('Acme Labs — Privacy', false);
        $this->get('/terms')->assertSee('href="mailto:hello@acme.test"', false)->assertSee('operated by Acme Labs');
        $this->get('/')->assertSee('"telephone":"+27215550100"', false);

        $log = AdminAuditLog::sole();
        $this->assertSame('company_settings.updated', $log->action);
        $this->assertSame('Company settings', $log->target_label);
        $this->assertStringContainsString('name', $log->details['changed']);

        // Saving the same details again changes nothing and logs nothing.
        $this->actingAs($admin)->put('/admin/company', $this->form())->assertSessionHas('status', 'No changes to save.');
        $this->assertSame(1, AdminAuditLog::count());
    }

    public function test_saved_details_are_loaded_on_the_next_request(): void
    {
        Setting::create(['key' => 'company', 'value' => ['name' => 'Stored Co', 'address' => ['city' => 'Pune']]]);
        Cache::flush();

        CompanySettings::apply();

        $this->assertSame('Stored Co', config('company.name'));
        $this->assertSame('Pune', config('company.address.city'));
        // Anything not saved keeps the config file's default.
        $this->assertSame('India', config('company.address.country'));
    }

    public function test_the_details_are_validated(): void
    {
        $this->actingAs($this->admin())->put('/admin/company', $this->form([
            'email' => 'not-an-email',
            'phone' => '94288 89935',
            'website' => 'briskbraintech.com',
            'country_code' => 'IND',
            'city' => '',
        ]))->assertSessionHasErrors(['email', 'phone', 'website', 'country_code', 'city']);

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_optional_address_parts_can_be_left_empty(): void
    {
        $this->actingAs($this->admin())->put('/admin/company', $this->form(['region' => '', 'postal_code' => '']))
            ->assertSessionHasNoErrors();

        $this->get('/about')->assertSee('Cape Town<br>', false);
    }
}
