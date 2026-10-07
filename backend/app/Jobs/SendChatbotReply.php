<?php

namespace App\Jobs;

use App\Models\ChatbotPause;
use App\Models\ChatbotRule;
use App\Models\Message;
use App\Notifications\ChatbotHandoff;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use App\Support\ChatbotMenu;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chatbot: answers one received message. Queued by WorkerWebhookController
 * (only for a real phone number, never a LID).
 *
 * - Numbered menu (when on): a menu word ("menu") gets the numbered list;
 *   a number right after it gets that entry's answer; "0" hands the chat
 *   to a person (see answerMenu()).
 * - A keyword matches: the best entry's answer is sent (any time of day),
 *   with its file if it has one.
 * - No match, and business hours are on and it's outside them: the
 *   "we're closed" message is sent (once per person per few hours).
 * - Otherwise nothing is sent.
 *
 * Nothing at all is sent while the chat is paused because the owner
 * replied by hand from their phone (see ChatbotPause).
 *
 * Re-checks everything at send time, since the owner may have switched the
 * bot off or the instance may have disconnected since the message arrived.
 */
class SendChatbotReply implements ShouldQueue
{
    use Queueable;

    // Sending is not safe to repeat (the reply may already be out).
    public int $tries = 1;

    public function __construct(public int $incomingMessageId) {}

    public function handle(MessageSender $sender, PlanLimiter $limiter): void
    {
        $incoming = Message::with('whatsappSession.user')->find($this->incomingMessageId);
        $session = $incoming?->whatsappSession;

        if (! $incoming
            || $incoming->direction !== 'incoming'
            || ! $session->chatbot_enabled
            || $session->status !== 'connected'
            || $this->tooManyReplies($incoming)
            // The owner replied by hand in this chat recently.
            || $session->chatbotPauses()->where('phone', $incoming->from_number)->where('paused_until', '>', now())->exists()
            || ! $limiter->canSendMessage($session->user)
            // Plan downgraded to one without the chatbot.
            || $limiter->chatbotEntryLimit($session->user) === 0) {
            return;
        }

        $text = in_array($incoming->type, ChatbotRule::REPLY_TO_TYPES, true) && trim($incoming->body) !== ''
            ? $incoming->body
            : null;

        $menu = $session->chatbotMenu();
        if ($text !== null && $menu->enabled && $this->answerMenu($incoming, $text, $menu, $sender)) {
            return;
        }

        $rule = $text !== null ? ChatbotRule::bestMatch($session->chatbotRules()->get(), $text) : null;

        if ($rule) {
            $this->sendAnswer($incoming, $rule, $sender);

            return;
        }

        $hours = $session->chatbotHours();

        if ($hours->enabled
            && ! $hours->isOpen(now())
            && ! $this->sentRecently($incoming, ['bot_reply' => 'closed'], now()->subHours(ChatbotRule::CLOSED_MESSAGE_WAIT_HOURS))) {
            $reply = $sender->send($session, $incoming->from_number, $hours->message);
            $reply->update(['bot_reply' => 'closed']);
        }
    }

    /**
     * The numbered menu. Returns true when the message was handled here:
     *
     * - A number, while this person has a menu open (sent less than
     *   ChatbotMenu::VALID_MINUTES ago): that option's answer, or for "0"
     *   a hand-off to a person, or for a number not on the menu, the menu
     *   again.
     * - A menu word ("menu", "start"...): the menu.
     *
     * Anything else returns false, and the message is matched against the
     * entries' keywords as usual.
     */
    private function answerMenu(Message $incoming, string $text, ChatbotMenu $menu, MessageSender $sender): bool
    {
        $session = $incoming->whatsappSession;
        $choice = ChatbotMenu::choice($text);
        $state = $choice === null ? null : $session->chatbotMenuStates()
            ->where('phone', $incoming->from_number)
            ->where('expires_at', '>', now())
            ->first();

        if ($state) {
            if ($choice === 0 && $menu->humanOption) {
                $this->handOff($incoming, $menu, $sender);

                return true;
            }

            // The entry this number meant when the menu was sent, if it
            // still exists and is still switched on.
            $ruleId = $choice > 0 ? ($state->rule_ids[$choice - 1] ?? null) : null;
            $rule = $ruleId ? $session->chatbotRules()->whereKey($ruleId)->where('enabled', true)->first() : null;

            if ($rule) {
                $this->sendAnswer($incoming, $rule, $sender);
                // They may want another option: keep the menu open a while longer.
                $state->update(['expires_at' => now()->addMinutes(ChatbotMenu::VALID_MINUTES)]);

                return true;
            }

            return $this->sendMenu($incoming, $menu, $sender);
        }

        return $menu->isRequestedBy($text) && $this->sendMenu($incoming, $menu, $sender);
    }

    /**
     * Sends the numbered menu and remembers what its numbers mean for this
     * person. False (nothing sent) when it would be empty.
     */
    private function sendMenu(Message $incoming, ChatbotMenu $menu, MessageSender $sender): bool
    {
        $session = $incoming->whatsappSession;
        $options = ChatbotMenu::options($session->chatbotRules()->get());

        if ($options->isEmpty() && ! $menu->humanOption) {
            return false;
        }

        $reply = $sender->send($session, $incoming->from_number, $menu->text($options));
        $reply->update(['bot_reply' => 'menu']);

        $session->chatbotMenuStates()->updateOrCreate(
            ['phone' => $incoming->from_number],
            ['rule_ids' => $options->pluck('id')->all(), 'expires_at' => now()->addMinutes(ChatbotMenu::VALID_MINUTES)],
        );

        return true;
    }

    /**
     * "0. Talk to a person": tell the customer someone will reply, pause the
     * bot in this chat (as if the owner had replied by hand) and email the
     * owner. The pause uses the owner's own setting, or 1 hour when they
     * chose "Don't pause": a person was asked for, so the bot must step back.
     */
    private function handOff(Message $incoming, ChatbotMenu $menu, MessageSender $sender): void
    {
        $session = $incoming->whatsappSession;
        $phone = $incoming->from_number;
        $minutes = $session->chatbot_pause_minutes > 0 ? $session->chatbot_pause_minutes : ChatbotMenu::HUMAN_PAUSE_FALLBACK_MINUTES;

        $reply = $sender->send($session, $phone, $menu->humanReply);
        $reply->update(['bot_reply' => 'handoff']);

        $session->chatbotPauses()->updateOrCreate(['phone' => $phone], ['paused_until' => now()->addMinutes($minutes)]);
        $session->chatbotMenuStates()->where('phone', $phone)->delete();

        // The customer already has their reply; a mail problem must not undo that.
        try {
            $session->user->notify(new ChatbotHandoff($session, $phone, ChatbotPause::DURATIONS[$minutes] ?? "{$minutes} minutes"));
        } catch (Throwable $e) {
            Log::warning('Could not email the chatbot hand-off', ['instance_id' => $session->instance_id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * An entry's answer, with its file if it has one (the answer is then the
     * caption). Not sent again to the same person within a couple of minutes.
     */
    private function sendAnswer(Message $incoming, ChatbotRule $rule, MessageSender $sender): void
    {
        if ($this->sentRecently($incoming, ['chatbot_rule_id' => $rule->id], now()->subMinutes(ChatbotRule::REPEAT_WAIT_MINUTES))) {
            return;
        }

        $media = $rule->media();
        $reply = $sender->send($incoming->whatsappSession, $incoming->from_number, $rule->answer, type: $media ? $rule->media_type : 'text', media: $media);
        $reply->update(['chatbot_rule_id' => $rule->id, 'bot_reply' => 'answer']);
    }

    /**
     * This person already got a bot reply matching $where since $since.
     */
    private function sentRecently(Message $incoming, array $where, DateTimeInterface $since): bool
    {
        return $this->botRepliesTo($incoming)->where($where)->where('created_at', '>=', $since)->exists();
    }

    /**
     * MAX_REPLIES_PER_CONTACT_PER_HOUR reached — so two bots answering each
     * other can't loop forever.
     */
    private function tooManyReplies(Message $incoming): bool
    {
        return $this->botRepliesTo($incoming)->where('created_at', '>=', now()->subHour())->count()
            >= ChatbotRule::MAX_REPLIES_PER_CONTACT_PER_HOUR;
    }

    private function botRepliesTo(Message $incoming): HasMany
    {
        return $incoming->whatsappSession->messages()
            ->where('direction', 'outgoing')
            ->where('to_number', $incoming->from_number)
            ->whereNotNull('bot_reply');
    }
}
