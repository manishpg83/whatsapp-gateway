<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once when an account reaches 80% of its plan's monthly message
 * limit, and once more at 100% — so customers can upgrade before sending
 * stops instead of finding out from failing API calls. See UsageWarner.
 */
class UsageLimitWarning extends Notification
{
    use Queueable;

    public function __construct(
        public int $percent,
        public int $used,
        public int $limit,
        public string $planName,
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
        $resetsOn = now()->startOfMonth()->addMonthNoOverflow()->format('F j, Y');
        $usage = number_format($this->used).' of '.number_format($this->limit);

        if ($this->percent >= 100) {
            return (new MailMessage)
                ->subject("You've reached your monthly message limit")
                ->greeting("Hi {$notifiable->name},")
                ->line("You've used all {$usage} messages included in your {$this->planName} plan this month.")
                ->line('New messages sent through the API will be refused until your limit resets on '.$resetsOn.'.')
                ->line('Upgrade your plan to keep sending right away — the new limit applies immediately.')
                ->action('Upgrade plan', route('billing.index'));
        }

        return (new MailMessage)
            ->subject("You've used {$this->percent}% of your monthly messages")
            ->greeting("Hi {$notifiable->name},")
            ->line("You've sent {$usage} messages included in your {$this->planName} plan this month ({$this->percent}%).")
            ->line('When you reach the limit, new messages will be refused until it resets on '.$resetsOn.'.')
            ->line('If you expect to send more, upgrade now so nothing stops unexpectedly.')
            ->action('View plans', route('billing.index'));
    }
}
