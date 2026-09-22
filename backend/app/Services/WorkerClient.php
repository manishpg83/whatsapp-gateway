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

    /**
     * Sends a text message synchronously (no queue yet, per CLAUDE.md
     * §13 M7) and returns WhatsApp's message id. Throws on any failure —
     * network, worker error, or the instance not actually being connected
     * in the worker's memory — the caller decides how to record that.
     */
    public function sendMessage(string $instanceId, string $to, string $message): string
    {
        $response = $this->http()
            ->post("/sessions/{$instanceId}/messages", ['to' => $to, 'message' => $message])
            ->throw();

        return $response->json('message_id');
    }
}
