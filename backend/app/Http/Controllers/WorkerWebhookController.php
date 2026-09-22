<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
            'message' => ['required', 'string'],
            'whatsapp_message_id' => ['required', 'string'],
            'timestamp' => ['required', 'date'],
        ]);

        $session = $this->findByInstanceId($data['instance_id']);

        $message = $session->messages()->create([
            'direction' => 'incoming',
            'from_number' => $data['from'],
            'body' => $data['message'],
            'status' => 'received',
            'whatsapp_message_id' => $data['whatsapp_message_id'],
        ]);

        $this->webhookDispatcher->dispatch($session, [
            'event' => 'message.received',
            'instance_id' => $session->instance_id,
            'from' => $message->from_number,
            'message' => $message->body,
            'message_id' => $message->whatsapp_message_id,
            'timestamp' => $data['timestamp'],
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
