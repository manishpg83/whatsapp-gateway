<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NumberCheckTest extends TestCase
{
    use RefreshDatabase;

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function check(?string $token, array $payload): TestResponse
    {
        $headers = $token ? ['Authorization' => "Bearer {$token}"] : [];

        return $this->withHeaders($headers)->postJson('/api/v1/numbers/check', $payload);
    }

    private function fakeWorker(): void
    {
        Http::fake(['*/check-numbers' => Http::response(['results' => [
            ['number' => '919876543210', 'exists' => true, 'whatsapp_number' => '919876543210'],
            ['number' => '12499793168', 'exists' => false, 'whatsapp_number' => null],
        ]], 200)]);
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->check(null, ['instance_id' => 'x', 'numbers' => ['919876543210']])->assertUnauthorized();
    }

    public function test_it_returns_one_result_per_number(): void
    {
        $this->fakeWorker();
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $token] = ApiToken::generateFor($instance, 'x');

        $this->check($token, ['instance_id' => $instance->instance_id, 'numbers' => ['919876543210', '12499793168']])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'results' => [
                    ['number' => '919876543210', 'on_whatsapp' => true, 'whatsapp_number' => '919876543210'],
                    ['number' => '12499793168', 'on_whatsapp' => false, 'whatsapp_number' => null],
                ],
            ]);

        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:3001/sessions/{$instance->instance_id}/check-numbers"
            && $request['numbers'] === ['919876543210', '12499793168']);
    }

    public function test_bad_or_too_many_numbers_are_rejected(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $token] = ApiToken::generateFor($instance, 'x');

        foreach ([[], ['+91 98765 43210'], array_fill(0, 21, '919876543210')] as $numbers) {
            $this->check($token, ['instance_id' => $instance->instance_id, 'numbers' => $numbers])
                ->assertStatus(422);
        }

        Http::assertNothingSent();
    }

    public function test_instance_id_mismatch_is_rejected(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->connected()->create();
        $other = WhatsappSession::factory()->connected()->create();
        ['plainText' => $token] = ApiToken::generateFor($instance, 'x');

        $this->check($token, ['instance_id' => $other->instance_id, 'numbers' => ['919876543210']])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_disconnected_instance_is_rejected(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $token] = ApiToken::generateFor($instance, 'x');
        $instance->update(['status' => 'disconnected']);

        $this->check($token, ['instance_id' => $instance->instance_id, 'numbers' => ['919876543210']])
            ->assertStatus(422)
            ->assertJson(['error' => 'Instance is not connected']);
    }

    public function test_worker_failure_returns_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('worker down'));
        $instance = WhatsappSession::factory()->connected()->create();
        ['plainText' => $token] = ApiToken::generateFor($instance, 'x');

        $this->check($token, ['instance_id' => $instance->instance_id, 'numbers' => ['919876543210']])
            ->assertStatus(502);
    }

    public function test_dashboard_check_shows_the_result(): void
    {
        Http::fake(['*/check-numbers' => Http::response(['results' => [
            ['number' => '919876543210', 'exists' => true, 'whatsapp_number' => '919876543210'],
        ]], 200)]);

        $user = User::factory()->create();
        $instance = WhatsappSession::factory()->for($user)->connected()->create();

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('instances.check-number', $instance), ['check_number' => '919876543210'])
            ->assertOk()
            ->assertSee('is on WhatsApp');
    }

    public function test_dashboard_check_is_scoped_to_the_owner(): void
    {
        Http::fake();
        $instance = WhatsappSession::factory()->connected()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('instances.check-number', $instance), ['check_number' => '919876543210'])
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
