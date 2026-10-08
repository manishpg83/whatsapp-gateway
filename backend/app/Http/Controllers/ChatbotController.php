<?php

namespace App\Http\Controllers;

use App\Exceptions\MediaFetchException;
use App\Models\ChatbotPause;
use App\Models\ChatbotRule;
use App\Models\Message;
use App\Models\WhatsappSession;
use App\Services\MediaFetcher;
use App\Services\PlanLimiter;
use App\Support\ChatbotCsv;
use App\Support\ChatbotHours;
use App\Support\ChatbotMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chatbot: per-instance keyword → answer entries. Always scoped to the
 * logged-in user — another user's instance or entry is a 404 (CLAUDE.md §5).
 */
class ChatbotController extends Controller
{
    // "Unanswered questions": how far back to look, and how many to show.
    private const UNANSWERED_DAYS = 7;

    private const UNANSWERED_SHOWN = 10;

    private const MEDIA_COLUMNS = ['media_type', 'media_path', 'media_mime_type', 'media_file_name', 'media_size'];

    public function __construct(
        protected PlanLimiter $limiter,
        protected MediaFetcher $fetcher,
    ) {}

    public function index(Request $request): View
    {
        $instances = $request->user()->whatsappSessions()->withCount('chatbotRules')->orderBy('name')->get();

        // The instance picked in the switcher, else the one picked last time
        // (remembered in the session, so the sidebar link doesn't jump back
        // to the first instance), else the first one. Looked up in the
        // user's own list only, so a foreign id just falls back.
        $selected = $instances->firstWhere('instance_id', $request->query('instance'))
            ?? $instances->firstWhere('instance_id', $request->session()->get('chatbot.instance'))
            ?? $instances->first();

        if ($selected) {
            $request->session()->put('chatbot.instance', $selected->instance_id);
        }

        // Stats count only replies that actually went out (not failed ones).
        $sent = fn ($query) => $query->whereIn('status', Message::SENT_STATUSES);
        $weekAgo = now()->subDays(7);

        $rules = $selected?->chatbotRules()
            ->withCount([
                'replies as replies_total' => $sent,
                'replies as replies_week' => fn ($query) => $sent($query)->where('created_at', '>=', $weekAgo),
            ])
            ->withMax(['replies as last_reply_at' => $sent], 'created_at')
            ->get() ?? collect();

        $unanswered = $selected ? $this->unansweredQuestions($selected, $rules) : collect();

        $botReplies = $selected?->messages()->whereNotNull('bot_reply')->whereIn('status', Message::SENT_STATUSES);

        return view('chatbot.index', [
            'instances' => $instances,
            'selected' => $selected,
            'rules' => $rules,
            'unanswered' => $unanswered,
            // "Add as entry" on an unanswered question pre-fills the form.
            'prefillQuestion' => Str::limit((string) $request->query('question', ''), 150, ''),
            'max' => ChatbotRule::MAX_PER_INSTANCE,
            // The plan's limit, across all instances.
            'planLimit' => $this->limiter->chatbotEntryLimit($request->user()),
            'planUsed' => $this->limiter->chatbotEntriesUsed($request->user()),
            'pauses' => $selected?->chatbotPauses()->where('paused_until', '>', now())->orderBy('paused_until')->get() ?? collect(),
            'stats' => $selected ? [
                'answers_week' => (clone $botReplies)->where('bot_reply', 'answer')->where('created_at', '>=', $weekAgo)->count(),
                'closed_week' => (clone $botReplies)->where('bot_reply', 'closed')->where('created_at', '>=', $weekAgo)->count(),
                'month' => (clone $botReplies)->where('created_at', '>=', now()->subDays(30))->count(),
            ] : null,
        ]);
    }

    public function store(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $data = $this->validated($request);
        $planLimit = $this->limiter->chatbotEntryLimit($request->user());

        if (! $this->limiter->canAddChatbotEntry($request->user())) {
            throw ValidationException::withMessages(['question' => $planLimit === 0
                ? "The chatbot isn't included in your plan. Upgrade to use it."
                : "Your plan allows {$planLimit} chatbot ".Str::plural('entry', $planLimit).'. Upgrade to add more.']);
        }

        if ($whatsappSession->chatbotRules()->count() >= ChatbotRule::MAX_PER_INSTANCE) {
            throw ValidationException::withMessages(['question' => 'This instance already has '.ChatbotRule::MAX_PER_INSTANCE.' entries. Delete one first.']);
        }

        // New entries go to the bottom of the list.
        $data['position'] = (int) $whatsappSession->chatbotRules()->max('position') + 1;

        // Stored last, once everything else is valid, so a rejected form
        // never leaves an unused file behind.
        $whatsappSession->chatbotRules()->create($data + ($this->uploadedMedia($request, $whatsappSession) ?? []));

        return $this->backToList($whatsappSession, "Added \"{$data['question']}\".");
    }

    public function edit(Request $request, string $instance, int $rule): View
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        return view('chatbot.edit', [
            'instance' => $whatsappSession,
            'rule' => $this->findRule($whatsappSession, $rule),
        ]);
    }

    public function update(Request $request, string $instance, int $rule): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $model = $this->findRule($whatsappSession, $rule);
        $data = $this->validated($request);

        // A new file replaces the old one; "Remove" goes back to text only.
        // The old file stays on disk: replies already sent still link to it.
        $media = $this->uploadedMedia($request, $whatsappSession);
        if ($media !== null) {
            $data += $media;
        } elseif ($request->boolean('remove_media')) {
            $data += array_fill_keys(self::MEDIA_COLUMNS, null);
        }

        $model->update($data);

        return $this->backToList($whatsappSession, "Updated \"{$model->question}\".");
    }

    public function destroy(Request $request, string $instance, int $rule): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $model = $this->findRule($whatsappSession, $rule);
        $model->delete();

        return $this->backToList($whatsappSession, "Deleted \"{$model->question}\".");
    }

    /**
     * All of an instance's entries as a CSV (in list order) — a backup, or
     * to edit in Excel and import again. With no entries it's an empty
     * template with just the column names. Attached files aren't included.
     */
    public function export(Request $request, string $instance): Response
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $name = Str::slug($whatsappSession->name) ?: 'instance';

        return response(ChatbotCsv::export($whatsappSession->chatbotRules()->get()), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"chatbot-{$name}.csv\"",
        ]);
    }

    /**
     * Adds entries from a CSV. A row whose question matches an existing
     * entry (any case) updates that entry instead — so an exported file can
     * be edited and imported back. All or nothing: if any row has a problem,
     * or the new entries don't fit the limits, nothing is imported.
     */
    public function import(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $request->validate([
            'csv' => ['required', 'file', 'max:2048', 'extensions:csv,txt'],
        ], [
            'csv.required' => 'Choose the CSV file to import.',
            'csv.extensions' => 'Upload a .csv file (in Excel: File → Save As → CSV UTF-8).',
            'csv.max' => 'The CSV file is too large (max 2 MB).',
        ]);

        [$rows, $errors] = ChatbotCsv::parse((string) file_get_contents($request->file('csv')->getRealPath()));

        if ($errors) {
            throw ValidationException::withMessages(['csv' => $errors]);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['csv' => 'The file has no entries — add rows below the column names.']);
        }

        $existing = $whatsappSession->chatbotRules()->get()->keyBy(fn (ChatbotRule $rule) => mb_strtolower($rule->question));
        $newCount = collect($rows)->reject(fn (array $row) => $existing->has(mb_strtolower($row['question'])))->count();

        $user = $request->user();
        $planLimit = $this->limiter->chatbotEntryLimit($user);
        $planLeft = $planLimit - $this->limiter->chatbotEntriesUsed($user);
        $instanceLeft = ChatbotRule::MAX_PER_INSTANCE - $existing->count();

        if ($newCount > 0 && $planLimit === 0) {
            throw ValidationException::withMessages(['csv' => "The chatbot isn't included in your plan. Upgrade to use it."]);
        }
        if ($newCount > $planLeft) {
            throw ValidationException::withMessages(['csv' => "The file has {$newCount} new ".Str::plural('entry', $newCount).', but your plan has room for '.max(0, $planLeft).' more. Remove some rows, or upgrade your plan.']);
        }
        if ($newCount > $instanceLeft) {
            throw ValidationException::withMessages(['csv' => "The file has {$newCount} new ".Str::plural('entry', $newCount).', but this instance has room for '.max(0, $instanceLeft).' more (max '.ChatbotRule::MAX_PER_INSTANCE.').']);
        }

        DB::transaction(function () use ($whatsappSession, $rows, $existing) {
            $position = (int) $whatsappSession->chatbotRules()->max('position');

            foreach ($rows as $row) {
                $data = ['question' => $row['question'], 'keywords' => $row['keywords'], 'answer' => $row['answer']];
                $rule = $existing->get(mb_strtolower($row['question']));

                if ($rule) {
                    // No status column/cell: keep the entry's current setting.
                    $rule->update($data + ($row['enabled'] === null ? [] : ['enabled' => $row['enabled']]));
                } else {
                    $whatsappSession->chatbotRules()->create($data + ['position' => ++$position, 'enabled' => $row['enabled'] ?? true]);
                }
            }
        });

        $updated = count($rows) - $newCount;
        $summary = collect([
            $newCount ? "added {$newCount} ".Str::plural('entry', $newCount) : null,
            $updated ? "updated {$updated}" : null,
        ])->filter()->implode(' and ');

        return $this->backToList($whatsappSession, 'Import done: '.$summary.'.');
    }

    /**
     * Switches one entry on or off. A switched-off entry stays in the list
     * (with its stats) but the bot skips it.
     */
    public function toggleRule(Request $request, string $instance, int $rule): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $model = $this->findRule($whatsappSession, $rule);
        $model->update(['enabled' => $request->boolean('enabled')]);

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#rule-'.$model->id)
            ->with('status', $model->enabled ? "\"{$model->question}\" is ON." : "\"{$model->question}\" is OFF — the bot skips it.");
    }

    /**
     * Moves an entry one place up or down. The order decides which entry
     * answers when two match equally (higher wins). Every entry is
     * renumbered 1, 2, 3... so the positions never drift or collide.
     */
    public function move(Request $request, string $instance, int $rule): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $model = $this->findRule($whatsappSession, $rule);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = $whatsappSession->chatbotRules()->pluck('id')->all();
        $from = array_search($model->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            DB::transaction(function () use ($whatsappSession, $ids) {
                foreach ($ids as $index => $id) {
                    $whatsappSession->chatbotRules()->whereKey($id)->update(['position' => $index + 1]);
                }
            });
        }

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#rule-'.$model->id);
    }

    /**
     * An entry's attached file, for its owner only. Same safety headers as
     * BulkCampaignController::media(): documents always download.
     */
    public function media(Request $request, string $instance, int $rule): StreamedResponse
    {
        $model = $this->findRule($this->findOwnedInstance($request, $instance), $rule);

        abort_unless($model->hasMedia(), 404);

        $inline = $model->mediaIsInline() && ! $request->boolean('download');

        return Storage::disk('whatsapp_media')->response(
            $model->media_path,
            $model->media_file_name ?: $model->media_type.'-'.basename($model->media_path),
            [
                'Content-Type' => $inline ? $model->media_mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
                'Cache-Control' => 'private, max-age=3600',
            ],
            $inline ? 'inline' : 'attachment'
        );
    }

    /**
     * The ON/OFF switch. While ON, received messages that match an entry
     * are answered automatically (see SendChatbotReply).
     */
    public function toggle(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $enabled = $request->boolean('enabled');

        if ($enabled && $this->limiter->chatbotEntryLimit($request->user()) === 0) {
            return $this->backToList($whatsappSession, null)->withErrors(['enabled' => "The chatbot isn't included in your plan. Upgrade to use it."]);
        }

        if ($enabled && ! $whatsappSession->chatbotRules()->where('enabled', true)->exists() && ! $whatsappSession->chatbotHours()->enabled) {
            return $this->backToList($whatsappSession, null)->withErrors(['enabled' => 'Add at least one entry that is switched on (or turn on business hours) before switching the chatbot on.']);
        }

        $whatsappSession->update(['chatbot_enabled' => $enabled]);

        return $this->backToList($whatsappSession, $enabled ? 'Chatbot is ON — matching messages now get an automatic reply.' : 'Chatbot is OFF — no automatic replies.');
    }

    /**
     * Business hours: outside them, messages that match no entry get the
     * "we're closed" message (see SendChatbotReply).
     */
    public function updateHours(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $enabled = $request->boolean('hours_enabled');

        $data = $request->validate([
            'days' => [Rule::requiredIf($enabled), 'array'],
            'days.*' => ['integer', 'between:1,7'],
            'open' => ['required', 'date_format:H:i'],
            'close' => ['required', 'date_format:H:i', 'different:open'],
            'timezone' => ['required', 'timezone:all'],
            'message' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:1000'],
        ], [
            'days.required' => 'Pick at least one open day.',
            'close.different' => 'Closing time must be different from opening time.',
            'message.required' => 'Write the message to send when you\'re closed.',
        ]);

        $whatsappSession->update(['chatbot_hours' => [
            'enabled' => $enabled,
            'days' => array_values(array_unique(array_map('intval', $data['days'] ?? []))),
            'open' => $data['open'],
            'close' => $data['close'],
            'timezone' => $data['timezone'],
            'message' => $data['message'] ?? ChatbotHours::DEFAULTS['message'],
        ]]);

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#hours')
            ->with('status', $enabled ? 'Business hours saved.' : 'Business hours are off.');
    }

    /**
     * The numbered menu's settings (see App\Support\ChatbotMenu). Its
     * options are the entries ticked "Show in menu".
     */
    public function updateMenu(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $enabled = $request->boolean('menu_enabled');
        $humanOption = $request->boolean('human_option');

        $data = $request->validate([
            'intro' => ['required', 'string', 'max:500'],
            'menu_keywords' => ['required', 'string', 'max:500'],
            'human_reply' => [Rule::requiredIf($humanOption), 'nullable', 'string', 'max:1000'],
        ], [
            'intro.required' => 'Write the line shown above the options.',
            'menu_keywords.required' => 'Add at least one menu word.',
            'human_reply.required' => 'Write the reply for "Talk to a person".',
        ]);

        $keywords = ChatbotRule::parseKeywords($data['menu_keywords']);
        if (count($keywords) > ChatbotMenu::MAX_KEYWORDS) {
            throw ValidationException::withMessages(['menu_keywords' => 'Use at most '.ChatbotMenu::MAX_KEYWORDS.' menu words.']);
        }
        if ($error = ChatbotRule::keywordsError($keywords)) {
            throw ValidationException::withMessages(['menu_keywords' => $error]);
        }

        $options = ChatbotMenu::options($whatsappSession->chatbotRules()->get());
        if ($enabled && $options->isEmpty() && ! $humanOption) {
            throw ValidationException::withMessages(['menu_enabled' => 'Tick "Show in menu" on at least one entry first (or turn on "0. Talk to a person").']);
        }

        $whatsappSession->update(['chatbot_menu' => [
            'enabled' => $enabled,
            'intro' => $data['intro'],
            'keywords' => $keywords,
            'human_option' => $humanOption,
            'human_reply' => $data['human_reply'] ?? ChatbotMenu::DEFAULTS['human_reply'],
        ]]);

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#menu')
            ->with('status', $enabled ? 'Numbered menu saved.' : 'Numbered menu is off.');
    }

    /**
     * How long the bot stays quiet in a chat after the owner replies there
     * from their own phone (0 = never pause).
     */
    public function updatePause(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $data = $request->validate([
            'pause_minutes' => ['required', 'integer', Rule::in(array_keys(ChatbotPause::DURATIONS))],
        ]);

        $whatsappSession->update(['chatbot_pause_minutes' => $data['pause_minutes']]);

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#pause')
            ->with('status', $data['pause_minutes'] > 0
                ? 'The bot will pause for '.ChatbotPause::DURATIONS[$data['pause_minutes']].' after you reply yourself.'
                : "The bot won't pause when you reply yourself.");
    }

    /**
     * Ends one chat's pause early, so the bot answers there again.
     */
    public function resume(Request $request, string $instance, int $pause): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $whatsappSession->chatbotPauses()->whereKey($pause)->firstOrFail()->delete();

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#pause')
            ->with('status', 'The bot is answering in that chat again.');
    }

    /**
     * "Test bot" box: shows which entry would answer a sample message,
     * without sending anything on WhatsApp.
     */
    public function test(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $data = $request->validate([
            'test_message' => ['required', 'string', 'max:4096'],
        ], [
            'test_message.required' => 'Type a message to test.',
        ]);

        $rules = $whatsappSession->chatbotRules()->get();
        $menu = $whatsappSession->chatbotMenu();
        $options = ChatbotMenu::options($rules);
        $choice = ChatbotMenu::choice($data['test_message']);

        // Same order as the real bot: the menu first, then the keywords.
        if ($menu->enabled && ($menu->isRequestedBy($data['test_message']) || $choice !== null)) {
            return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#test')
                ->with('chatbot_test', [
                    'message' => $data['test_message'],
                    'menu_text' => $choice === null ? $menu->text($options) : null,
                    'menu_choice' => $choice,
                    'rule_id' => $choice > 0 ? $options->get($choice - 1)?->id : null,
                    'handoff' => $choice === 0 && $menu->humanOption,
                    'keywords' => [],
                ]);
        }

        $rule = ChatbotRule::bestMatch($rules, $data['test_message']);

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#test')
            ->with('chatbot_test', [
                'message' => $data['test_message'],
                'rule_id' => $rule?->id,
                // No answer, but a switched-off entry would have matched: say so.
                'off_rule_id' => $rule ? null : $rules->first(fn (ChatbotRule $r) => ! $r->enabled && $r->matches($data['test_message']))?->id,
                'keywords' => $rule?->matchedKeywords($data['test_message']) ?? [],
            ]);
    }

    /**
     * Recent received messages that none of the current entries would
     * answer — so the owner can see what to add next. Checked against
     * today's entries, so a message drops off as soon as one matches it.
     * The same text sent several times is shown once, with a count.
     *
     * @param  Collection<int, ChatbotRule>  $rules
     * @return Collection<int, array{body: string, count: int, last_at: Carbon, from: string}>
     */
    private function unansweredQuestions(WhatsappSession $whatsappSession, Collection $rules): Collection
    {
        $menu = $whatsappSession->chatbotMenu();

        return $whatsappSession->messages()
            ->where('direction', 'incoming')
            ->whereIn('type', ChatbotRule::REPLY_TO_TYPES)
            ->where('body', '!=', '')
            ->where('created_at', '>=', now()->subDays(self::UNANSWERED_DAYS))
            ->latest('id')
            ->limit(300)
            ->get(['body', 'from_number', 'created_at'])
            ->reject(fn (Message $message) => ChatbotRule::bestMatch($rules, $message->body) !== null)
            // With the menu on, "menu" and "2" are answered by the menu, not left unanswered.
            ->reject(fn (Message $message) => $menu->enabled && ($menu->isRequestedBy($message->body) || ChatbotMenu::choice($message->body) !== null))
            ->groupBy(fn (Message $message) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $message->body))))
            ->map(fn (Collection $same) => [
                'body' => trim($same->first()->body),
                'count' => $same->count(),
                'last_at' => $same->first()->created_at,
                'from' => $same->first()->from_number,
            ])
            ->take(self::UNANSWERED_SHOWN)
            ->values();
    }

    /**
     * @return array{question: string, keywords: list<string>, answer: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:150'],
            'keywords' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:4096'],
            // Size and real file type are checked by MediaFetcher (uploadedMedia()).
            'media' => ['nullable', 'file'],
        ], [
            'keywords.required' => 'Add at least one keyword.',
            'answer.required' => 'Write the answer to send.',
            'media.file' => 'The file could not be uploaded (it may be larger than the server allows: '.ini_get('upload_max_filesize').').',
            'media.uploaded' => 'The file could not be uploaded (it may be larger than the server allows: '.ini_get('upload_max_filesize').').',
        ]);
        unset($data['media']);

        $keywords = ChatbotRule::parseKeywords($data['keywords']);

        if ($error = ChatbotRule::keywordsError($keywords)) {
            throw ValidationException::withMessages(['keywords' => $error]);
        }

        $data['keywords'] = $keywords;
        $data['in_menu'] = $request->boolean('in_menu');

        return $data;
    }

    /**
     * Saves the uploaded attachment (if any) and returns its columns. The
     * kind (image / video / document) comes from the file's real content.
     *
     * @return array<string, mixed>|null
     */
    private function uploadedMedia(Request $request, WhatsappSession $whatsappSession): ?array
    {
        $file = $request->file('media');

        if (! $file) {
            return null;
        }

        $type = ChatbotRule::mediaTypeFor($file);

        try {
            $media = $this->fetcher->fromUpload($whatsappSession, $file, $type);
        } catch (MediaFetchException $e) {
            throw ValidationException::withMessages(['media' => $e->getMessage()]);
        }

        return [
            'media_type' => $type,
            'media_path' => $media['path'],
            'media_mime_type' => $media['mime_type'],
            'media_file_name' => $media['file_name'],
            'media_size' => $media['size'],
        ];
    }

    private function findOwnedInstance(Request $request, string $instanceId): WhatsappSession
    {
        return $request->user()->whatsappSessions()->where('instance_id', $instanceId)->firstOrFail();
    }

    private function findRule(WhatsappSession $whatsappSession, int $id): ChatbotRule
    {
        return $whatsappSession->chatbotRules()->whereKey($id)->firstOrFail();
    }

    private function backToList(WhatsappSession $whatsappSession, ?string $status): RedirectResponse
    {
        $redirect = redirect()->route('chatbot.index', ['instance' => $whatsappSession->instance_id]);

        return $status === null ? $redirect : $redirect->with('status', $status);
    }
}
