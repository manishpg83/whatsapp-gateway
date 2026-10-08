<?php

namespace App\Http\Controllers;

use App\Exceptions\MediaFetchException;
use App\Models\ChatbotPause;
use App\Models\InboxConversation;
use App\Models\Message;
use App\Models\WhatsappSession;
use App\Services\MediaFetcher;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inbox: the instance's messages grouped into conversations, one per
 * customer number — the list on the left, the open chat on the right.
 *
 * A conversation is every message from that number (incoming) or to it
 * (outgoing: API, bulk, chatbot). Messages the owner types on their own
 * phone aren't stored, so they don't appear here.
 *
 * Only the logged-in user's own instances — a foreign instance id just
 * falls back to their own (CLAUDE.md §5).
 */
class InboxController extends Controller
{
    // Conversations listed, newest first.
    private const CONVERSATIONS_SHOWN = 100;

    // Messages shown in the open chat (the latest ones).
    private const MESSAGES_SHOWN = 100;

    public function index(Request $request): View
    {
        $instances = $request->user()->whatsappSessions()->orderBy('name')->get();

        // As on the Chatbot page: the instance picked, else the one picked
        // last time, else the first.
        $selected = $instances->firstWhere('instance_id', $request->query('instance'))
            ?? $instances->firstWhere('instance_id', $request->session()->get('inbox.instance'))
            ?? $instances->first();

        if ($selected) {
            $request->session()->put('inbox.instance', $selected->instance_id);
        }

        [$search, $chat] = $this->searchAndChat($request);

        if ($selected && $chat !== null) {
            InboxConversation::markRead($selected, $chat);
        }

        return view('inbox.index', [
            'instances' => $instances,
            'selected' => $selected,
            'search' => $search,
            'chat' => $chat,
            ...($selected ? $this->listData($selected, $search) + $this->threadData($selected, $chat) : [
                'conversations' => collect(), 'thread' => collect(), 'threadTotal' => 0, 'chatName' => null, 'botPause' => null,
            ]),
        ]);
    }

    /**
     * Live updates, polled every few seconds by inbox.js: the conversation
     * list and the open chat, as HTML — each only when it changed since the
     * version the page already shows — plus the unread total.
     *
     * `seen=1` (the page is visible to the owner) marks the open chat read.
     */
    public function updates(Request $request, string $instance): JsonResponse
    {
        $session = $request->user()->whatsappSessions()->where('instance_id', $instance)->firstOrFail();
        [$search, $chat] = $this->searchAndChat($request);

        if ($chat !== null && $request->boolean('seen')) {
            InboxConversation::markRead($session, $chat);
        }

        $list = $this->listData($session, $search);
        $thread = $this->threadData($session, $chat);
        $view = ['selected' => $session, 'search' => $search, 'chat' => $chat];

        return response()->json([
            'list_version' => $list['listVersion'],
            'list' => $list['listVersion'] === $request->query('list')
                ? null
                : view('inbox._rows', $view + $list)->render(),
            'thread_version' => $thread['threadVersion'],
            'thread' => $chat === null || $thread['threadVersion'] === $request->query('thread')
                ? null
                : view('inbox._thread', $view + $thread)->render(),
            'thread_total' => number_format($thread['threadTotal']).' '.Str::plural('message', $thread['threadTotal']),
            'unread_total' => InboxConversation::unreadFor($request->user()),
        ]);
    }

    /**
     * The reply box: sends a text — or a file, with the text as its
     * caption — to the open chat's number, sent and stored like any other
     * message (counts toward the plan, gets ticks). Only into an existing
     * conversation — this isn't a "message anyone" form. Like a reply typed
     * on the phone, it pauses the chatbot in that chat (the instance's
     * "Pause when you reply yourself" setting).
     */
    public function reply(Request $request, string $instance, MessageSender $sender, PlanLimiter $limiter, MediaFetcher $fetcher): RedirectResponse
    {
        $session = $request->user()->whatsappSessions()->where('instance_id', $instance)->firstOrFail();

        $data = $request->validate([
            'chat' => ['required', 'regex:/^\d{5,20}$/'],
            'message' => [Rule::requiredIf(! $request->hasFile('file')), 'nullable', 'string', 'max:4096'],
            // Size and real file type are checked by MediaFetcher below.
            'file' => ['nullable', 'file'],
        ], [
            'message.required' => 'Type a message first.',
        ]);

        $back = redirect()->route('inbox.index', ['instance' => $session->instance_id, 'chat' => $data['chat']]);

        abort_unless($this->conversation($session, $data['chat'])->exists(), 404);

        if ($session->status !== 'connected') {
            return $back->withInput()->with('error', 'This instance is not connected. Reconnect it to reply.');
        }

        if (! $limiter->canSendMessage($request->user())) {
            return $back->withInput()->with('error', "You've reached your plan's monthly message limit. Upgrade to send more.");
        }

        $type = 'text';
        $media = null;

        if ($request->hasFile('file')) {
            $type = self::fileType((string) $request->file('file')->getMimeType());

            try {
                $media = $fetcher->fromUpload($session, $request->file('file'), $type, $request->file('file')->getClientOriginalName());
            } catch (MediaFetchException $e) {
                return $back->withInput()->withErrors(['file' => $e->getMessage()]);
            }
        }

        $message = $sender->send($session, $data['chat'], (string) ($data['message'] ?? ''), type: $type, media: $media, allowFallback: true);
        InboxConversation::markRead($session, $data['chat']);

        if ($session->chatbot_pause_minutes > 0) {
            ChatbotPause::extend($session, $data['chat'], $session->chatbot_pause_minutes);
        }

        return match (true) {
            $message->fallback_status === 'sent' => $back->with('status', 'The linked device could not send it, so it was sent through the Cloud API fallback.'),
            $message->status === 'failed' => $back->withInput()->with('error', 'Your reply could not be sent. Check that the instance is connected, then try again.'),
            default => $back,
        };
    }

    /**
     * The chat header's bot switch: turn the chatbot off for just this
     * customer (a long pause — see ChatbotPause::OFF_YEARS), or back on
     * (ends any pause, including one from a recent reply).
     */
    public function bot(Request $request, string $instance): RedirectResponse
    {
        $session = $request->user()->whatsappSessions()->where('instance_id', $instance)->firstOrFail();

        $data = $request->validate([
            'chat' => ['required', 'regex:/^\d{5,20}$/'],
            'bot' => ['required', 'in:on,off'],
        ]);

        abort_unless($this->conversation($session, $data['chat'])->exists(), 404);

        if ($data['bot'] === 'off') {
            $session->chatbotPauses()->updateOrCreate(['phone' => $data['chat']], ['paused_until' => now()->addYears(ChatbotPause::OFF_YEARS)]);
        } else {
            $session->chatbotPauses()->where('phone', $data['chat'])->delete();
        }

        return redirect()->route('inbox.index', ['instance' => $session->instance_id, 'chat' => $data['chat']])
            ->with('status', $data['bot'] === 'off'
                ? 'The chatbot is off for this chat — it won\'t reply to this customer until you turn it back on.'
                : 'The chatbot answers this customer again.');
    }

    /**
     * The message type for an uploaded file, from its real (detected) type:
     * photos and videos WhatsApp can show inline, audio as a file; anything
     * else (PDF, Excel, …) is sent as a document.
     */
    private static function fileType(string $mime): string
    {
        foreach (['image', 'video', 'audio'] as $type) {
            if (in_array($mime, MediaFetcher::RULES[$type][1], true)) {
                return $type;
            }
        }

        return 'document';
    }

    /**
     * The search box and the open chat's number, digits only (null = none).
     *
     * @return array{0: string, 1: ?string}
     */
    private function searchAndChat(Request $request): array
    {
        $chat = preg_replace('/\D/', '', (string) $request->query('chat', ''));

        return [preg_replace('/\D/', '', (string) $request->query('search', '')), $chat !== '' ? $chat : null];
    }

    /**
     * The conversation list, and a version string that changes whenever
     * anything shown in it does (new message, tick, unread count).
     *
     * @return array{conversations: Collection, listVersion: string}
     */
    private function listData(WhatsappSession $session, string $search): array
    {
        $conversations = $this->conversations($session, $search);

        return [
            'conversations' => $conversations,
            'listVersion' => md5($conversations->map(fn ($c) => [$c->contact, $c->name, $c->last->id, $c->last->status, $c->unread])->toJson()),
        ];
    }

    /**
     * The open chat's latest messages, their total, a version string that
     * changes with any new message or status (tick) change, the customer's
     * name, and the chatbot's pause in this chat (null = answering).
     *
     * @return array{thread: Collection, threadTotal: int, threadVersion: string, chatName: ?string, botPause: ?ChatbotPause}
     */
    private function threadData(WhatsappSession $session, ?string $chat): array
    {
        if ($chat === null) {
            return ['thread' => collect(), 'threadTotal' => 0, 'threadVersion' => '', 'chatName' => null, 'botPause' => null];
        }

        $thread = $this->conversation($session, $chat)->latest('id')->take(self::MESSAGES_SHOWN)->get()->reverse()->values();

        return [
            'thread' => $thread,
            'threadTotal' => $this->conversation($session, $chat)->count(),
            'threadVersion' => md5($thread->map(fn (Message $m) => [$m->id, $m->status, $m->fallback_status])->toJson()),
            'chatName' => InboxConversation::where('whatsapp_session_id', $session->id)->where('phone', $chat)->value('name'),
            'botPause' => $session->chatbotPauses()->where('phone', $chat)->where('paused_until', '>', now())->first(),
        ];
    }

    /**
     * One row per customer number: its latest message, message count,
     * unread count and WhatsApp profile name — newest first.
     *
     * @return Collection<int, object{contact: string, name: ?string, last: Message, total: int, unread: int}>
     */
    private function conversations(WhatsappSession $session, string $search): Collection
    {
        $contact = "CASE WHEN direction = 'incoming' THEN from_number ELSE to_number END";

        $rows = $session->messages()
            ->selectRaw("{$contact} as contact, MAX(id) as last_id, COUNT(*) as total")
            ->when($search !== '', fn ($query) => $query->whereRaw("{$contact} LIKE ?", ["%{$search}%"]))
            ->groupByRaw($contact)
            ->orderByDesc('last_id')
            ->take(self::CONVERSATIONS_SHOWN)
            ->toBase()
            ->get();

        $last = Message::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');
        $info = InboxConversation::where('whatsapp_session_id', $session->id)
            ->whereIn('phone', $rows->pluck('contact')->filter())
            ->get(['phone', 'name', 'unread_count'])
            ->keyBy('phone');

        return $rows
            ->filter(fn ($row) => $row->contact !== null && $row->contact !== '' && $last->has($row->last_id))
            ->map(fn ($row) => (object) [
                'contact' => (string) $row->contact,
                'name' => $info[$row->contact]->name ?? null,
                'last' => $last[$row->last_id],
                'total' => (int) $row->total,
                'unread' => (int) ($info[$row->contact]->unread_count ?? 0),
            ])
            ->values();
    }

    /**
     * Every message between this instance and $phone.
     *
     * @return Builder<Message>
     */
    private function conversation(WhatsappSession $session, string $phone): Builder
    {
        return Message::where('whatsapp_session_id', $session->id)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('direction', 'incoming')->where('from_number', $phone))
                ->orWhere(fn ($q) => $q->whereIn('direction', ['outgoing', Message::DIRECTION_PHONE])->where('to_number', $phone)));
    }
}
