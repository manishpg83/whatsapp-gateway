<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use App\Models\WhatsappSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Chatbot: per-instance keyword → answer entries. Always scoped to the
 * logged-in user — another user's instance or entry is a 404 (CLAUDE.md §5).
 */
class ChatbotController extends Controller
{
    public function index(Request $request): View
    {
        $instances = $request->user()->whatsappSessions()->orderBy('name')->get();

        // The instance picked in the dropdown, else the first one. Looked up
        // in the user's own list only, so a foreign id just falls back.
        $selected = $instances->firstWhere('instance_id', $request->query('instance')) ?? $instances->first();

        return view('chatbot.index', [
            'instances' => $instances,
            'selected' => $selected,
            'rules' => $selected?->chatbotRules()->get() ?? collect(),
            'max' => ChatbotRule::MAX_PER_INSTANCE,
        ]);
    }

    public function store(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);
        $data = $this->validated($request);

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

        if ($enabled && ! $whatsappSession->chatbotRules()->exists()) {
            return $this->backToList($whatsappSession, null)->withErrors(['enabled' => 'Add at least one entry before switching the chatbot on.']);
        }

        $whatsappSession->update(['chatbot_enabled' => $enabled]);

        return $this->backToList($whatsappSession, $enabled ? 'Chatbot is ON — matching messages now get an automatic reply.' : 'Chatbot is OFF — no automatic replies.');
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
