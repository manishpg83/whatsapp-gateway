<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin → Email Templates → "Send test email": an already-built template
 * email, sent straight to the admin's own address.
 */
class EmailTemplateTest extends Notification
{
    public function __construct(public MailMessage $mail) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail;
    }
}
