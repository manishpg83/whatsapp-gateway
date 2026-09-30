<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms a paid subscription was cancelled — whether the user cancelled
 * from the Billing page (BillingController) or Cashfree reported it, e.g.
 * the mandate was revoked in their UPI / bank app (CashfreeWebhookController).
 */
class SubscriptionCancelled extends Notification
{
    use Queueable;

    public function __construct(public string $planName) {}

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
            ->subject("Your {$this->planName} subscription has been cancelled")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$this->planName} subscription has been cancelled. No further payments will be taken for it.")
            ->line('Your account is now on the Free plan limits. Your instances, API tokens and message history are kept.')
            ->line('Changed your mind? You can subscribe again anytime from the Billing page.')
            ->action('Go to Billing', route('billing.index'))
            ->line("If you didn't cancel this yourself, please contact us: ".route('contact', ['topic' => 'billing']));
    }
}
