<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use App\Services\WorkerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class InstanceController extends Controller
{
    public function index(Request $request): View
    {
        $instances = $request->user()->whatsappSessions()->latest()->get();

        return view('instances.index', ['instances' => $instances]);
    }

    public function create(): View
    {
        return view('instances.create');
    }

    public function store(Request $request, WorkerClient $worker, PlanLimiter $limiter): RedirectResponse
    {
        if (! $limiter->canCreateInstance($request->user())) {
            return redirect()->route('instances.create')
                ->with('error', "You've reached your plan's instance limit. Upgrade to add more.");
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $instance = $request->user()->whatsappSessions()->create([
            'name' => $data['name'],
            'status' => 'connecting',
        ]);

        try {
            $worker->startSession($instance->instance_id);
        } catch (Throwable $e) {
            Log::error('Worker unreachable while starting a session', [
                'instance_id' => $instance->instance_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('instances.show', $instance)
                ->with('error', 'Could not reach the WhatsApp worker. Is it running? Use "Reconnect" to retry.');
        }

        return redirect()->route('instances.show', $instance);
    }

    public function show(Request $request, string $instance): View
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        return view('instances.show', [
            'instance' => $whatsappSession,
            'messages' => $whatsappSession->messages()->latest()->take(20)->get(),
            'sentCount' => $whatsappSession->messages()->where('direction', 'outgoing')->where('status', 'sent')->count(),
            'failedCount' => $whatsappSession->messages()->where('direction', 'outgoing')->where('status', 'failed')->count(),
            'receivedCount' => $whatsappSession->messages()->where('direction', 'incoming')->count(),
        ]);
    }

    /**
     * Sets or clears the webhook URL that incoming messages get forwarded
     * to. A signing secret is generated the first time a URL is set, and
     * kept (not regenerated) on later updates so the owner's receiving
     * end doesn't need to change anything just because the URL changed.
     */
    public function updateWebhook(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $webhookUrl = $data['webhook_url'] ?? null;

        $whatsappSession->update([
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $webhookUrl
                ? ($whatsappSession->webhook_secret ?? Str::random(40))
                : null,
        ]);

        return redirect()->route('instances.show', $whatsappSession)
            ->with('status', $webhookUrl ? 'Webhook saved.' : 'Webhook cleared.');
    }

    /**
     * The dashboard's own "send a test message" button — a convenience
     * for trying the connection out without needing curl/Postman. Uses
     * the exact same MessageSender the public API uses underneath, so
     * this is also a live example of what that API actually does.
     */
    public function sendTestMessage(Request $request, string $instance, MessageSender $sender, PlanLimiter $limiter): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        if ($whatsappSession->status !== 'connected') {
            return redirect()->route('instances.show', $whatsappSession)
                ->with('error', 'Connect this instance before sending a message.');
        }

        if (! $limiter->canSendMessage($request->user())) {
            return redirect()->route('instances.show', $whatsappSession)
                ->with('error', "You've reached your plan's monthly message limit. Upgrade to send more.");
        }

        $data = $request->validate([
            'to' => ['required', 'regex:/^\d{7,15}$/'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $message = $sender->send($whatsappSession, $data['to'], $data['message']);

        return redirect()->route('instances.show', $whatsappSession)->with(
            $message->status === 'sent' ? 'status' : 'error',
            $message->status === 'sent'
                ? 'Message sent.'
                : 'Could not send message. Is the worker running and this instance actually connected?'
        );
    }

    /**
     * Polled by the browser every 2-3 seconds on the show page.
     */
    public function status(Request $request, string $instance): JsonResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        return response()->json([
            'status' => $whatsappSession->status,
            'qr_code' => $whatsappSession->qr_code,
            'phone_number' => $whatsappSession->phone_number,
        ]);
    }

    public function destroy(Request $request, string $instance, WorkerClient $worker): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        try {
            $worker->stopSession($whatsappSession->instance_id);
        } catch (Throwable $e) {
            Log::error('Worker unreachable while stopping a session', [
                'instance_id' => $whatsappSession->instance_id,
                'error' => $e->getMessage(),
            ]);
        }

        $whatsappSession->update(['status' => 'disconnected']);

        return redirect()->route('instances.index')->with('status', 'Instance disconnected.');
    }

    public function reconnect(Request $request, string $instance, WorkerClient $worker): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        $whatsappSession->update(['status' => 'connecting', 'qr_code' => null]);

        try {
            $worker->startSession($whatsappSession->instance_id);
        } catch (Throwable $e) {
            Log::error('Worker unreachable while reconnecting a session', [
                'instance_id' => $whatsappSession->instance_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('instances.show', $whatsappSession)
                ->with('error', 'Could not reach the WhatsApp worker. Is it running?');
        }

        return redirect()->route('instances.show', $whatsappSession);
    }

    /**
     * Looks up an instance scoped to the current user (query-level
     * defence) and then double-checks ownership explicitly (CLAUDE.md §5:
     * scope in the query AND an explicit check). A UUID belonging to
     * another user, or one that doesn't exist at all, both 404 — never
     * confirm to a caller that someone else's instance exists.
     */
    protected function findOwnedInstance(Request $request, string $instanceId): WhatsappSession
    {
        $whatsappSession = $request->user()->whatsappSessions()
            ->where('instance_id', $instanceId)
            ->firstOrFail();

        abort_unless($whatsappSession->user_id === $request->user()->id, 404);

        return $whatsappSession;
    }
}
