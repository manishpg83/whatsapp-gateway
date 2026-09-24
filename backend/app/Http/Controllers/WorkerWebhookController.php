<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\WhatsappSession;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives status updates from the Node worker: a new QR code, a
 * connection state change, or an incoming message. Protected by the
 * internal.secret middleware (see routes/web.php) — never by session
 * auth, since the worker has no browser session.
 */
class WorkerWebhookController extends Controller
{
    public function __construct(protected WebhookDispatcher $webhookDispatcher) {}

    public function __invoke(Request $request): Response
    {
        return match ($request->string('event')->toString()) {
            'qr.updated' => $this->handleQrUpdated($request),
            'connection.updated' => $this->handleConnectionUpdated($request),
            'message.received' => $this->handleMessageReceived($request),
            default => response('Unknown event', 422),
        };
    }

    protected function handleQrUpdated(Request $request): Response
    {
        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'qr_code' => ['required', 'string'],
        ]);

        $session = $this->findByInstanceId($data['instance_id']);

        $session->update([
            'status' => 'qr_pending',
            'qr_code' => $data['qr_code'],
            'qr_updated_at' => now(),
        ]);

        return response()->noContent();
    }

    protected function handleConnectionUpdated(Request $request): Response
    {
        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'status' => ['required', 'in:connected,disconnected,logged_out'],
            'phone_number' => ['nullable', 'string'],
            'last_disconnect_reason' => ['nullable', 'string'],
        ]);

        $session = $this->findByInstanceId($data['instance_id']);
        $connected = $data['status'] === 'connected';

        $session->update([
            'status' => $data['status'],
            'phone_number' => $connected ? ($data['phone_number'] ?? null) : $session->phone_number,
            'connected_at' => $connected ? now() : $session->connected_at,
            'last_disconnect_reason' => $data['last_disconnect_reason'] ?? null,
            // A QR left over from before this connection/disconnect is stale either way.
            'qr_code' => null,
        ]);

        return response()->noContent();
    }

    protected function handleMessageReceived(Request $request): Response
    {
        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'from' => ['required', 'string'],
            // Older workers didn't send a type — they only ever sent text.
            'type' => ['sometimes', 'in:'.implode(',', array_keys(Message::TYPES))],
            // Text, caption or summary. May be empty (e.g. a photo with no caption).
            'message' => ['present', 'nullable', 'string'],
            'whatsapp_message_id' => ['required', 'string'],
            'timestamp' => ['required', 'date'],
            'media' => ['nullable', 'array'],
            'media.status' => ['required_with:media', 'in:stored,too_large,failed'],
            'media.path' => ['nullable', 'string', 'max:255'],
            'media.mime_type' => ['required_with:media', 'string', 'max:255'],
            'media.file_name' => ['nullable', 'string', 'max:255'],
            'media.size' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = $this->findByInstanceId($data['instance_id']);
        $media = $data['media'] ?? null;

        // Defence in depth: even though only the worker (holding the shared
        // secret) can call this, never trust a file path blindly — it must
        // point inside THIS instance's own folder, with no "../" tricks.
        $mediaPath = $media['path'] ?? null;
        if ($mediaPath !== null && ! preg_match('#^'.preg_quote($session->instance_id, '#').'/[A-Za-z0-9_-]+\.[a-z0-9]{1,10}$#', $mediaPath)) {
            Log::warning('Worker sent an invalid media path; ignoring the file', ['instance_id' => $session->instance_id]);
            $mediaPath = null;
            $media['status'] = 'failed';
        }

        if ($media !== null && $media['status'] === 'stored' && $mediaPath === null) {
            $media['status'] = 'failed'; // "stored" but nowhere to find it
        }

        $message = $session->messages()->create([
            'direction' => 'incoming',
            'type' => $data['type'] ?? 'text',
            'from_number' => $data['from'],
            'body' => $data['message'] ?? '',
            'status' => 'received',
            'whatsapp_message_id' => $data['whatsapp_message_id'],
            'media_status' => $media['status'] ?? null,
            'media_path' => $mediaPath,
            'media_mime_type' => $media['mime_type'] ?? null,
            'media_file_name' => $media['file_name'] ?? null,
            'media_size' => $media['size'] ?? null,
        ]);

        $this->webhookDispatcher->dispatch($session, [
            'event' => 'message.received',
            'instance_id' => $session->instance_id,
            'from' => $message->from_number,
            'type' => $message->type,
            'message' => $message->body,
            'message_id' => $message->whatsapp_message_id,
            'timestamp' => $data['timestamp'],
            'media' => $media === null ? null : [
                'status' => $message->media_status,
                'mime_type' => $message->media_mime_type,
                'file_name' => $message->media_file_name,
                'size' => $message->media_size,
                // A 24-hour download link (no login needed) — only when the file was stored.
                'url' => $message->media_status === 'stored' && $mediaPath !== null ? $message->temporaryMediaUrl() : null,
            ],
        ]);

        return response()->noContent();
    }

    /**
     * 404s on an unknown instance_id rather than silently ignoring it —
     * a wrong id here almost certainly means a bug, and Laravel is the
     * source of truth for which instances exist.
     */
    protected function findByInstanceId(string $instanceId): WhatsappSession
    {
        return WhatsappSession::where('instance_id', $instanceId)->firstOrFail();
    }
}
