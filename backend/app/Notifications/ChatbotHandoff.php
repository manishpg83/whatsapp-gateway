<?php

namespace App\Notifications;

use App\Models\WhatsappSession;
use App\Services\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the instance owner when a customer replies "0" (talk to a
 * person) to the chatbot menu — so they know someone is waiting.
 */
class ChatbotHandoff extends Notification
{
    use Queueable;

    public function __construct(
        public WhatsappSession $instance,
        public string $customerPhone,
        public string $pauseTime,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplates::mail('chatbot_handoff', [
            'name' => $notifiable->name,
            'customer_phone' => '+'.$this->customerPhone,
            'instance_name' => $this->instance->name,
            'pause_time' => $this->pauseTime,
        ], route('messages.index'));
    }
}
