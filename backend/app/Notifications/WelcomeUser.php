<?php

namespace App\Notifications;

use App\Services\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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
        $plan = $notifiable->subscription->planDetails();

        return EmailTemplates::mail('welcome', [
            'name' => $notifiable->name,
            'plan_name' => $plan['name'],
            'instances' => $plan['instances'].' '.Str::plural('instance', $plan['instances']),
            'messages_per_month' => number_format($plan['messages_per_month']),
        ], route('dashboard'));
    }
}
