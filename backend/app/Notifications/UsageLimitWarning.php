<?php

namespace App\Notifications;

use App\Services\EmailTemplates;
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
        return EmailTemplates::mail($this->percent >= 100 ? 'usage_100' : 'usage_80', [
            'name' => $notifiable->name,
            'plan_name' => $this->planName,
            'percent' => (string) $this->percent,
            'used' => number_format($this->used),
            'limit' => number_format($this->limit),
            'reset_date' => now()->startOfMonth()->addMonthNoOverflow()->format('F j, Y'),
        ], route('billing.index'));
    }
}
