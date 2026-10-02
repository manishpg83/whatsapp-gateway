<?php

namespace App\Notifications;

use App\Services\EmailTemplates;
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
        return EmailTemplates::mail('subscription_cancelled', [
            'name' => $notifiable->name,
            'plan_name' => $this->planName,
        ], route('billing.index'));
    }
}
