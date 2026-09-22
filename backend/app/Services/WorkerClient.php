<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the Node.js worker over its internal HTTP API. This is the
 * ONLY place in the Laravel app that should know the worker's URL/secret
 * — nothing else in this codebase should call WhatsApp directly
 * (CLAUDE.md §2: "Laravel must NEVER talk to WhatsApp directly").
 */
class WorkerClient
{
    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('worker.base_url'))
            ->withHeaders(['X-Internal-Secret' => config('worker.secret')])
            ->timeout(5);
    }

    /**
     * Asks the worker to start a Baileys session for this instance. The
     * worker reports QR codes / connection status back asynchronously via
     * POST /internal/worker/events, handled by our own webhook endpoint.
     */
    public function startSession(string $instanceId): void
    {
        $this->http()->post('/sessions', ['instance_id' => $instanceId])->throw();
    }

    /**
     * Asks the worker to stop (log out) the session for this instance.
     */
    public function stopSession(string $instanceId): void
    {
        $this->http()->delete("/sessions/{$instanceId}")->throw();
    }
}
