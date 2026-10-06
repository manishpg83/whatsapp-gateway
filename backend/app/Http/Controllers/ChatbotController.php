<?php

namespace App\Http\Controllers;

use App\Models\ChatbotPause;
use App\Models\ChatbotRule;
use App\Models\Message;
use App\Models\WhatsappSession;
use App\Services\PlanLimiter;
use App\Support\ChatbotHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Chatbot: per-instance keyword → answer entries. Always scoped to the
 * logged-in user — another user's instance or entry is a 404 (CLAUDE.md §5).
 */
class ChatbotController extends Controller
{
    public function __construct(protected PlanLimiter $limiter) {}

    public function index(Request $request): View
    {
        $instances = $request->user()->whatsappSessions()->orderBy('name')->get();

        // The instance picked in the dropdown, else the first one. Looked up
        // in the user's own list only, so a foreign id just falls back.
        $selected = $instances->firstWhere('instance_id', $request->query('instance')) ?? $instances->first();

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

        $botReplies = $selected?->messages()->whereNotNull('bot_reply')->whereIn('status', Message::SENT_STATUSES);

        return view('chatbot.index', [
            'instances' => $instances,
            'selected' => $selected,
            'rules' => $rules,
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

        $whatsappSession->chatbotRules()->create($data);

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
        $model->update($this->validated($request));

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

        if ($enabled && ! $whatsappSession->chatbotRules()->exists() && ! $whatsappSession->chatbotHours()->enabled) {
            return $this->backToList($whatsappSession, null)->withErrors(['enabled' => 'Add at least one entry (or turn on business hours) before switching the chatbot on.']);
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

        $rule = ChatbotRule::bestMatch($whatsappSession->chatbotRules()->get(), $data['test_message']);

        return redirect()->to(route('chatbot.index', ['instance' => $whatsappSession->instance_id]).'#test')
            ->with('chatbot_test', [
                'message' => $data['test_message'],
                'rule_id' => $rule?->id,
                'keywords' => $rule?->matchedKeywords($data['test_message']) ?? [],
            ]);
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
        ], [
            'keywords.required' => 'Add at least one keyword.',
            'answer.required' => 'Write the answer to send.',
        ]);

        $keywords = ChatbotRule::parseKeywords($data['keywords']);

        if ($keywords === []) {
            throw ValidationException::withMessages(['keywords' => 'Add at least one keyword.']);
        }
        if (count($keywords) > ChatbotRule::MAX_KEYWORDS) {
            throw ValidationException::withMessages(['keywords' => 'Use at most '.ChatbotRule::MAX_KEYWORDS.' keywords per entry.']);
        }
        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) > ChatbotRule::MAX_KEYWORD_LENGTH) {
                throw ValidationException::withMessages(['keywords' => 'Each keyword can be at most '.ChatbotRule::MAX_KEYWORD_LENGTH.' characters.']);
            }
        }

        $data['keywords'] = $keywords;

        return $data;
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
