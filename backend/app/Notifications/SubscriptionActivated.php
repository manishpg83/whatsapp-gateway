<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Services\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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

        return EmailTemplates::mail('subscription_activated', [
            'name' => $notifiable->name,
            'plan_name' => $plan['name'],
            'plan_price' => '₹'.number_format($plan['price']),
            'instances' => $plan['instances'].' '.Str::plural('instance', $plan['instances']),
            'messages_per_month' => number_format($plan['messages_per_month']),
            'renew_date' => $this->subscription->current_period_end?->format('F j, Y') ?? 'not scheduled',
        ], route('dashboard'));
    }
}
