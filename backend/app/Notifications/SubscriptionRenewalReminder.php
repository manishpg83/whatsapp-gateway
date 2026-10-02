<?php

namespace App\Notifications;

use App\Services\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * "Your plan renews in a few days" — sent once per billing period, a few
 * days before Cashfree charges the next monthly payment. Sent by the daily
 * `billing:renewal-reminders` command (routes/console.php).
 */
class SubscriptionRenewalReminder extends Notification
{
    use Queueable;

    public function __construct(
        public string $planName,
        public int $price,
        public Carbon $renewsOn,
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
        return EmailTemplates::mail('renewal_reminder', [
            'name' => $notifiable->name,
            'plan_name' => $this->planName,
            'amount' => '₹'.number_format($this->price),
            'renew_date' => $this->renewsOn->copy()->timezone('Asia/Kolkata')->format('F j, Y'),
        ], route('billing.index'));
    }
}
