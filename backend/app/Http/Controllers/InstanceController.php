<?php

namespace App\Http\Controllers;

use App\Exceptions\MediaFetchException;
use App\Models\Message;
use App\Models\WhatsappSession;
use App\Rules\PublicWebhookUrl;
use App\Services\MediaFetcher;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use App\Services\WebhookDispatcher;
use App\Services\WorkerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            // 10 per page; its own page name + #recent-messages so paging
            // jumps straight back to that card instead of the top of the page.
            'messages' => $whatsappSession->messages()->latest()->latest('id')
                ->paginate(10, pageName: 'messages_page')
                ->fragment('recent-messages'),
            'sentCount' => $whatsappSession->messages()->where('direction', 'outgoing')->whereIn('status', Message::SENT_STATUSES)->count(),
            'failedCount' => $whatsappSession->messages()->where('direction', 'outgoing')->where('status', 'failed')->count(),
            'receivedCount' => $whatsappSession->messages()->where('direction', 'incoming')->count(),
            'webhookDeliveries' => $whatsappSession->webhookDeliveries()->latest()->latest('id')->take(20)->get(),
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
            'webhook_url' => ['nullable', 'url', 'max:2048', new PublicWebhookUrl],
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
     * The "Send test webhook" button — one immediate signed request (no
     * queue), so the owner finds out right away whether their receiver
     * works. Recorded in the delivery log like any other delivery.
     */
    public function testWebhook(Request $request, string $instance, WebhookDispatcher $dispatcher): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        if (! $whatsappSession->webhook_url || ! $whatsappSession->webhook_secret) {
            return redirect()->route('instances.show', $whatsappSession)
                ->with('error', 'Save a webhook URL first.');
        }

        $delivery = $dispatcher->sendTest($whatsappSession);

        return $delivery->status === 'success'
            ? redirect()->route('instances.show', $whatsappSession)
                ->with('status', "Test webhook delivered — your server replied HTTP {$delivery->response_status}.")
            : redirect()->route('instances.show', $whatsappSession)
                ->with('error', "Test webhook failed: {$delivery->error}");
    }

    /**
     * The dashboard's own "send a test message" button — a convenience
     * for trying the connection out without needing curl/Postman. Uses
     * the exact same MessageSender the public API uses underneath, so
     * this is also a live example of what that API actually does.
     */
    public function sendTestMessage(Request $request, string $instance, MessageSender $sender, PlanLimiter $limiter, MediaFetcher $fetcher): RedirectResponse
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

        $isText = ($request->input('type') ?? 'text') === 'text';
        $upload = $request->file('media');
        $hasUpload = $upload instanceof UploadedFile;

        $data = $request->validate([
            'to' => ['required', 'regex:/^\d{7,15}$/'],
            'type' => ['sometimes', 'in:text,'.implode(',', array_keys(MediaFetcher::RULES))],
            'message' => [Rule::requiredIf($isText), 'nullable', 'string', 'max:4096'],
            // A dropped/chosen file wins; the link is only needed without one.
            'media_url' => [Rule::requiredIf(! $isText && ! $hasUpload), 'nullable', 'url', 'max:2048'],
        ], [
            'media_url.required' => 'Drop a file, choose one from your computer, or paste a link to it.',
        ]);

        $type = $data['type'] ?? 'text';
        $body = in_array($type, ['audio', 'voice'], true) ? '' : (string) ($data['message'] ?? '');
        $media = null;

        if (! $isText) {
            try {
                $media = $hasUpload
                    ? $fetcher->fromUpload($whatsappSession, $upload, $type)
                    : $fetcher->fetch($whatsappSession, $data['media_url'], $type);
            } catch (MediaFetchException $e) {
                return redirect()->route('instances.show', $whatsappSession)->withInput()->with('error', $e->getMessage());
            }
        }

        $message = $sender->send($whatsappSession, $data['to'], $body, null, $type, $media);

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
