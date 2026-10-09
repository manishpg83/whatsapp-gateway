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
    // Conversations listed, newest first — and how many more each
    // "Load more chats" adds (up to MAX_PAGES times).
    private const CONVERSATIONS_SHOWN = 100;

    // Messages shown in the open chat (the latest ones) — and how many
    // more each "Load earlier messages" adds (up to MAX_PAGES times).
    private const MESSAGES_SHOWN = 100;

    private const MAX_PAGES = 20;

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

        $view = $this->viewState($request);

        if ($selected && $view['chat'] !== null) {
            InboxConversation::markRead($selected, $view['chat']);
        }

        return view('inbox.index', [
            'instances' => $instances,
            'selected' => $selected,
            ...$view,
            'listParams' => $selected ? $this->listParams($selected, $view) : [],
            ...($selected ? $this->listData($selected, $view) + $this->threadData($selected, $view) : [
                'conversations' => collect(), 'hasMoreChats' => false, 'unreadChats' => 0, 'thread' => collect(), 'threadTotal' => 0,
                'hasOlder' => false, 'chatName' => null, 'customName' => null, 'botPause' => null,
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
        $view = $this->viewState($request);
        $chat = $view['chat'];

        if ($chat !== null && $request->boolean('seen')) {
            InboxConversation::markRead($session, $chat);
        }

        $list = $this->listData($session, $view);
        $thread = $this->threadData($session, $view);
        $view += ['selected' => $session, 'listParams' => $this->listParams($session, $view)];

        return response()->json([
            'list_version' => $list['listVersion'],
            'list' => $list['listVersion'] === $request->query('list')
                ? null
                : view('inbox._rows', $view + $list)->render(),
            'thread_version' => $thread['threadVersion'],
            'thread' => $chat === null || $thread['threadVersion'] === $request->query('thread')
                ? null
                : view('inbox._thread', $view + $list + $thread)->render(),
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
     * "Retry" under a failed message: sends the same text (and file, if it
     * is still stored) again, as a new message — the failed one stays, with
     * its error, as history. Same rules as a reply: connected instance,
     * within the plan, pauses the chatbot in this chat.
     */
    public function retry(Request $request, string $instance, int $message, MessageSender $sender, PlanLimiter $limiter): RedirectResponse
    {
        $session = $request->user()->whatsappSessions()->where('instance_id', $instance)->firstOrFail();
        // Only this instance's own failed outgoing messages (CLAUDE.md §5).
        $failed = $session->messages()->whereKey($message)->where('direction', 'outgoing')->where('status', 'failed')->firstOrFail();

        abort_unless($failed->canBeRetried(), 404);

        $back = redirect()->route('inbox.index', ['instance' => $session->instance_id, 'chat' => $failed->to_number]);

        if ($session->status !== 'connected') {
            return $back->with('error', 'This instance is not connected. Reconnect it to retry.');
        }

        if (! $limiter->canSendMessage($request->user())) {
            return $back->with('error', "You've reached your plan's monthly message limit. Upgrade to send more.");
        }

        $media = $failed->media_path ? [
            'path' => $failed->media_path,
            'mime_type' => $failed->media_mime_type,
            'file_name' => $failed->media_file_name,
            'size' => (int) $failed->media_size,
        ] : null;

        $sent = $sender->send($session, $failed->to_number, $failed->body, type: $failed->type, media: $media, allowFallback: true);

        if ($session->chatbot_pause_minutes > 0) {
            ChatbotPause::extend($session, $failed->to_number, $session->chatbot_pause_minutes);
        }

        return match (true) {
            $sent->fallback_status === 'sent' => $back->with('status', 'The linked device could not send it, so it was sent through the Cloud API fallback.'),
            $sent->status === 'failed' => $back->with('error', 'It failed again. Check that the instance is connected and the number is on WhatsApp.'),
            default => $back,
        };
    }

    /**
     * The chat header's ✏️: save the owner's own name for this customer, or
     * (empty / "Use WhatsApp name") go back to their WhatsApp profile name.
     */
    public function rename(Request $request, string $instance): RedirectResponse
    {
        $session = $request->user()->whatsappSessions()->where('instance_id', $instance)->firstOrFail();

        $data = $request->validate([
            'chat' => ['required', 'regex:/^\d{5,20}$/'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        abort_unless($this->conversation($session, $data['chat'])->exists(), 404);

        $name = $request->boolean('reset') ? '' : trim((string) ($data['name'] ?? ''));

        InboxConversation::updateOrCreate(
            ['whatsapp_session_id' => $session->id, 'phone' => $data['chat']],
            ['custom_name' => $name !== '' ? $name : null]
        );

        return redirect()->route('inbox.index', ['instance' => $session->instance_id, 'chat' => $data['chat']])
            ->with('status', $name !== '' ? 'Name saved.' : 'Showing their WhatsApp name again.');
    }

    /**
     * The chat header's "Mark as unread": the chat gets its unread badge
     * back (at least 1), to come back to later. Goes back to the list —
     * staying in the open chat would mark it read again straight away.
     */
    public function unread(Request $request, string $instance): RedirectResponse
    {
        $session = $request->user()->whatsappSessions()->where('instance_id', $instance)->firstOrFail();

        $data = $request->validate([
            'chat' => ['required', 'regex:/^\d{5,20}$/'],
        ]);

        abort_unless($this->conversation($session, $data['chat'])->exists(), 404);

        $conversation = InboxConversation::firstOrCreate(['whatsapp_session_id' => $session->id, 'phone' => $data['chat']]);
        $conversation->update(['unread_count' => max(1, $conversation->unread_count)]);

        return redirect()->route('inbox.index', array_filter([
            'instance' => $session->instance_id,
            'search' => $request->input('search') ?: null,
            'filter' => $request->input('filter') === 'unread' ? 'unread' : null,
        ]))->with('status', 'Marked as unread.');
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
     * What the page shows, from the query string: the search text, the
     * All / Unread filter, the open chat's number (digits only, null =
     * none), and how many pages of
     * conversations ("Load more chats") and of the chat's messages ("Load
     * earlier messages") are loaded.
     *
     * @return array{search: string, unread: bool, chat: ?string, chats: int, older: int}
     */
    private function viewState(Request $request): array
    {
        $chat = preg_replace('/\D/', '', (string) $request->query('chat', ''));
        $page = fn (string $key) => max(1, min(self::MAX_PAGES, (int) $request->query($key, 1)));

        return [
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 50),
            'unread' => $request->query('filter') === 'unread',
            'chat' => $chat !== '' ? $chat : null,
            'chats' => $page('chats'),
            'older' => $page('older'),
        ];
    }

    /**
     * The query string that keeps the list as it is (instance, search,
     * filter, chats loaded) — every Inbox link starts from this, so none
     * of them drops one by accident.
     *
     * @param  array{search: string, unread: bool, chats: int}  $view
     * @return array<string, string|int>
     */
    private function listParams(WhatsappSession $session, array $view): array
    {
        return array_filter([
            'instance' => $session->instance_id,
            'search' => $view['search'] ?: null,
            'filter' => $view['unread'] ? 'unread' : null,
            'chats' => $view['chats'] > 1 ? $view['chats'] : null,
        ]);
    }

    /**
     * The conversation list, and a version string that changes whenever
     * anything shown in it does (new message, tick, unread count).
     *
     * @param  array{search: string, unread: bool, chat: ?string, chats: int}  $view
     * @return array{conversations: Collection, hasMoreChats: bool, unreadChats: int, listVersion: string}
     */
    private function listData(WhatsappSession $session, array $view): array
    {
        $limit = self::CONVERSATIONS_SHOWN * $view['chats'];
        // One extra, to know whether there are more to load.
        $conversations = $this->conversations($session, $view, $limit + 1);
        $hasMore = $conversations->count() > $limit && $view['chats'] < self::MAX_PAGES;
        $conversations = $conversations->take($limit);
        // For the "Unread (3)" filter button.
        $unreadChats = InboxConversation::where('whatsapp_session_id', $session->id)->where('unread_count', '>', 0)->count();

        return [
            'conversations' => $conversations,
            'hasMoreChats' => $hasMore,
            'unreadChats' => $unreadChats,
            'listVersion' => md5($conversations->map(fn ($c) => [$c->contact, $c->name, $c->last->id, $c->last->status, $c->unread])->push($hasMore, $unreadChats)->toJson()),
        ];
    }

    /**
     * The open chat's latest messages, their total, a version string that
     * changes with any new message or status (tick) change, the customer's
     * name, and the chatbot's pause in this chat (null = answering).
     *
     * @param  array{chat: ?string, older: int}  $view
     * @return array{thread: Collection, threadTotal: int, hasOlder: bool, threadVersion: string, chatName: ?string, customName: ?string, botPause: ?ChatbotPause}
     */
    private function threadData(WhatsappSession $session, array $view): array
    {
        $chat = $view['chat'];

        if ($chat === null) {
            return ['thread' => collect(), 'threadTotal' => 0, 'hasOlder' => false, 'threadVersion' => '', 'chatName' => null, 'customName' => null, 'botPause' => null];
        }

        $thread = $this->conversation($session, $chat)->latest('id')->take(self::MESSAGES_SHOWN * $view['older'])->get()->reverse()->values();
        $total = $this->conversation($session, $chat)->count();
        $info = InboxConversation::where('whatsapp_session_id', $session->id)->where('phone', $chat)->first();

        return [
            'thread' => $thread,
            'threadTotal' => $total,
            'hasOlder' => $total > $thread->count() && $view['older'] < self::MAX_PAGES,
            'threadVersion' => md5($thread->map(fn (Message $m) => [$m->id, $m->status, $m->fallback_status])->toJson()),
            'chatName' => $info?->displayName(),
            'customName' => $info?->custom_name,
            'botPause' => $session->chatbotPauses()->where('phone', $chat)->where('paused_until', '>', now())->first(),
        ];
    }

    /**
     * One row per customer number: its latest message, unread count and
     * WhatsApp profile name — newest first, matching the search (a name or
     * a number) and the Unread filter (which keeps the open chat listed,
     * though opening it made it read). Read from inbox_conversations,
     * which remembers each chat's latest message (see
     * InboxConversation::messageAdded()), so this stays fast however many
     * messages the instance has.
     *
     * @return Collection<int, object{contact: string, name: ?string, last: Message, unread: int}>
     */
    private function conversations(WhatsappSession $session, array $view, int $limit): Collection
    {
        $search = $view['search'];
        $digits = preg_replace('/\D/', '', $search);
        // Typed text is matched literally — % and _ aren't wildcards.
        $text = addcslashes($search, '%_\\');

        $rows = InboxConversation::where('whatsapp_session_id', $session->id)
            ->whereNotNull('last_message_id')
            // A name ("Ramesh") or a number ("98111", "+91 98111").
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('custom_name', 'like', "%{$text}%")
                ->orWhere('name', 'like', "%{$text}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%"))))
            ->when($view['unread'], fn ($query) => $query->where(fn ($q) => $q
                ->where('unread_count', '>', 0)
                ->when($view['chat'] !== null, fn ($q) => $q->orWhere('phone', $view['chat']))))
            ->orderByDesc('last_message_id')
            ->take($limit)
            ->get(['phone', 'name', 'custom_name', 'unread_count', 'last_message_id']);

        $last = Message::whereIn('id', $rows->pluck('last_message_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn (InboxConversation $row) => $last->has($row->last_message_id))
            ->map(fn (InboxConversation $row) => (object) [
                'contact' => $row->phone,
                'name' => $row->displayName(),
                'last' => $last[$row->last_message_id],
                'unread' => $row->unread_count,
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
