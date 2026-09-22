<?php

namespace Tests\Feature;

use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalSessionsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-shared-secret-value-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config(['worker.secret' => self::SECRET]);
    }

    // Runs BEFORE RefreshDatabase wipes the database. Safety net: refuse to
    // continue unless we are on the dedicated test database.
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    public function test_rejects_a_missing_secret(): void
    {
        $this->getJson('/internal/worker/sessions')->assertUnauthorized();
    }

    public function test_rejects_the_wrong_secret(): void
    {
        $this->getJson('/internal/worker/sessions', ['X-Internal-Secret' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_returns_only_instances_that_should_be_live(): void
    {
        $connected = WhatsappSession::factory()->connected()->create();
        $connecting = WhatsappSession::factory()->create(['status' => 'connecting']);
        $qrPending = WhatsappSession::factory()->create(['status' => 'qr_pending']);
        $disconnected = WhatsappSession::factory()->create(['status' => 'disconnected']);
        $loggedOut = WhatsappSession::factory()->create(['status' => 'logged_out']);

        $response = $this->getJson('/internal/worker/sessions', ['X-Internal-Secret' => self::SECRET])
            ->assertOk();

        $instanceIds = $response->json('instance_ids');

        $this->assertContains($connected->instance_id, $instanceIds);
        $this->assertContains($connecting->instance_id, $instanceIds);
        $this->assertContains($qrPending->instance_id, $instanceIds);
        $this->assertNotContains($disconnected->instance_id, $instanceIds);
        $this->assertNotContains($loggedOut->instance_id, $instanceIds);
    }

    public function test_returns_an_empty_list_when_nothing_should_be_live(): void
    {
        WhatsappSession::factory()->create(['status' => 'disconnected']);

        $this->getJson('/internal/worker/sessions', ['X-Internal-Secret' => self::SECRET])
            ->assertOk()
            ->assertExactJson(['instance_ids' => []]);
    }
}
