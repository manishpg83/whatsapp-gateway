<?php

namespace App\Jobs;

use App\Models\ChatbotRule;
use App\Models\Message;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Chatbot: answers one received message. Queued by WorkerWebhookController
 * (only for a real phone number, never a LID).
 *
 * - A keyword matches: the best entry's answer is sent (any time of day).
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

        $rule = in_array($incoming->type, ChatbotRule::REPLY_TO_TYPES, true) && trim($incoming->body) !== ''
            ? ChatbotRule::bestMatch($session->chatbotRules()->get(), $incoming->body)
            : null;

        if ($rule) {
            if (! $this->sentRecently($incoming, ['chatbot_rule_id' => $rule->id], now()->subMinutes(ChatbotRule::REPEAT_WAIT_MINUTES))) {
                $reply = $sender->send($session, $incoming->from_number, $rule->answer);
                $reply->update(['chatbot_rule_id' => $rule->id, 'bot_reply' => 'answer']);
            }

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
