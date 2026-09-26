<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You're subscribed" — sent once when a newly purchased paid plan becomes
 * active (Cashfree confirmed the payment). See CashfreeWebhookController.
 */
class SubscriptionActivated extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->planDetails();

        return (new MailMessage)
            ->subject("You're subscribed to the {$plan['name']} plan")
            ->markdown('emails.subscription-activated', [
                'user' => $notifiable,
                'plan' => $plan,
                'renewsOn' => $this->subscription->current_period_end,
            ]);
    }
}
