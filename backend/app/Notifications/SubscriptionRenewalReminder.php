<?php

namespace App\Notifications;

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
        $date = $this->renewsOn->copy()->timezone('Asia/Kolkata')->format('F j, Y');
        $amount = '₹'.number_format($this->price);

        return (new MailMessage)
            ->subject("Your {$this->planName} plan renews on {$date}")
            ->greeting("Hi {$notifiable->name},")
            ->line("This is a reminder that your {$this->planName} plan renews automatically on {$date}.")
            ->line("{$amount} will be charged through Cashfree using the payment method you set up. You don't need to do anything.")
            ->line('Want to change or cancel your plan before then? You can do it anytime from the Billing page.')
            ->action('Manage your plan', route('billing.index'));
    }
}
