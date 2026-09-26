<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once, right after a new user verifies their email address — a
 * friendly "here's how to get started" guide. See
 * EmailVerificationController::verify() and users.welcome_sent_at.
 */
class WelcomeUser extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').' — let\'s send your first message')
            ->markdown('emails.welcome', [
                'user' => $notifiable,
                'plan' => $notifiable->subscription->planDetails(),
            ]);
    }
}
