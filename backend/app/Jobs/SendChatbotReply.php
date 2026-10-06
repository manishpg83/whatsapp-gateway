<?php

namespace App\Jobs;

use App\Models\ChatbotRule;
use App\Models\Message;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Chatbot: answers one received message when one of the instance's
 * entries has a keyword in it. No match = nothing is sent. Queued by
 * WorkerWebhookController (only for a real phone number, never a LID).
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
            || ! in_array($incoming->type, ChatbotRule::REPLY_TO_TYPES, true)
            || trim($incoming->body) === ''
            || ! $session->chatbot_enabled
            || $session->status !== 'connected') {
            return;
        }

        $rule = ChatbotRule::bestMatch($session->chatbotRules()->get(), $incoming->body);

        if (! $rule || $this->tooSoon($incoming, $rule) || ! $limiter->canSendMessage($session->user)) {
            return;
        }

        $reply = $sender->send($session, $incoming->from_number, $rule->answer);
        $reply->update(['chatbot_rule_id' => $rule->id]);
    }

    /**
     * The protections: this entry already went to this person within
     * REPEAT_WAIT_MINUTES, or they've had MAX_REPLIES_PER_CONTACT_PER_HOUR
     * bot replies in the last hour.
     */
    private function tooSoon(Message $incoming, ChatbotRule $rule): bool
    {
        $botReplies = $incoming->whatsappSession->messages()
            ->where('direction', 'outgoing')
            ->where('to_number', $incoming->from_number)
            ->whereNotNull('chatbot_rule_id');

        $sameAnswerRecently = (clone $botReplies)
            ->where('chatbot_rule_id', $rule->id)
            ->where('created_at', '>=', now()->subMinutes(ChatbotRule::REPEAT_WAIT_MINUTES))
            ->exists();

        return $sameAnswerRecently
            || (clone $botReplies)->where('created_at', '>=', now()->subHour())->count() >= ChatbotRule::MAX_REPLIES_PER_CONTACT_PER_HOUR;
    }
}
