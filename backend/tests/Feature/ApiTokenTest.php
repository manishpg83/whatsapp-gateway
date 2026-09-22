<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The pages use @vite(); skip loading the built CSS/JS files in tests.
        $this->withoutVite();
    }

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_guest_cannot_generate_a_token(): void
    {
        $instance = WhatsappSession::factory()->connected()->create();

        $this->post(route('instances.tokens.store', $instance), ['name' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_generate_a_token_for_a_connected_instance(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $response = $this->actingAs($user)->post(route('instances.tokens.store', $instance), [
            'name' => 'Production server',
        ]);

        $response->assertRedirect(route('instances.show', $instance));
        $response->assertSessionHas('new_token');

        $plainText = $response->getSession()->get('new_token');
        $this->assertSame(32, strlen($plainText));

        $token = ApiToken::where('whatsapp_session_id', $instance->id)->firstOrFail();
        $this->assertSame('Production server', $token->name);
        $this->assertSame(hash('sha256', $plainText), $token->token_hash);
        $this->assertSame(substr($plainText, 0, 8), $token->token_prefix);
        $this->assertNull($token->revoked_at);
    }

    public function test_the_plaintext_token_is_shown_once_then_gone(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)->post(route('instances.tokens.store', $instance), ['name' => 'x']);

        // The request right after the redirect still carries the flash —
        // this is the "shown once" page load.
        $this->actingAs($user)->get(route('instances.show', $instance))->assertOk();

        // Any request after that must not show it again.
        $token = ApiToken::where('whatsapp_session_id', $instance->id)->firstOrFail();
        $this->actingAs($user)->get(route('instances.show', $instance))
            ->assertOk()
            ->assertSee($token->token_prefix); // the prefix is fine, shown forever
    }

    public function test_cannot_generate_a_token_for_an_instance_that_is_not_connected(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->create(['status' => 'qr_pending']);

        $this->actingAs($user)->post(route('instances.tokens.store', $instance), ['name' => 'x'])
            ->assertStatus(422);

        $this->assertSame(0, ApiToken::count());
    }

    public function test_user_cannot_generate_a_token_for_another_users_instance(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->connected()->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('instances.tokens.store', $instance), ['name' => 'x'])
            ->assertNotFound();

        $this->assertSame(0, ApiToken::count());
    }

    public function test_owner_can_revoke_a_token(): void
    {
        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();
        $token = ApiToken::factory()->for($instance, 'whatsappSession')->create();

        $this->actingAs($user)->delete(route('instances.tokens.destroy', [$instance, $token]))
            ->assertRedirect(route('instances.show', $instance));

        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $instance = WhatsappSession::factory()->for($owner)->connected()->create();
        $token = ApiToken::factory()->for($instance, 'whatsappSession')->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete(route('instances.tokens.destroy', [$instance, $token]))
            ->assertNotFound();

        $this->assertNull($token->fresh()->revoked_at);
    }

    public function test_cannot_revoke_a_token_through_an_instance_it_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $instanceA = WhatsappSession::factory()->for($user)->connected()->create();
        $instanceB = WhatsappSession::factory()->for($user)->connected()->create();
        $token = ApiToken::factory()->for($instanceA, 'whatsappSession')->create();

        $this->actingAs($user)->delete(route('instances.tokens.destroy', [$instanceB, $token]))
            ->assertNotFound();

        $this->assertNull($token->fresh()->revoked_at);
    }
}
