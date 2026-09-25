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
     * Asks the worker to close the session but keep its credentials, so a
     * later Reconnect goes straight back in without a QR code.
     */
    public function disconnectSession(string $instanceId): void
    {
        $this->http()->post("/sessions/{$instanceId}/disconnect")->throw();
    }

    /**
     * Asks the worker to stop (log out) the session for this instance.
     */
    public function stopSession(string $instanceId): void
    {
        $this->http()->delete("/sessions/{$instanceId}")->throw();
    }

    /**
     * Sends a text or media message synchronously (no queue yet, per
     * CLAUDE.md §13 M7) and returns WhatsApp's message id. Throws on any
     * failure — network, worker error, or the instance not actually being
     * connected in the worker's memory — the caller decides how to record
     * that. For media, $message is the caption and $media points at a file
     * MediaFetcher already saved on the shared whatsapp_media disk.
     *
     * @param  array{path: string, mime_type: string, file_name: string|null}|null  $media
     */
    public function sendMessage(string $instanceId, string $to, string $message, string $type = 'text', ?array $media = null): string
    {
        $response = $this->http()
            // Uploading a big video/document to WhatsApp takes longer than a text.
            ->timeout($media ? 120 : 5)
            ->post("/sessions/{$instanceId}/messages", array_filter([
                'to' => $to,
                'type' => $type,
                'message' => $message,
                'media' => $media ? [
                    'path' => $media['path'],
                    'mime_type' => $media['mime_type'],
                    'file_name' => $media['file_name'],
                ] : null,
            ], fn ($value) => $value !== null))
            ->throw();

        return $response->json('message_id');
    }
}
