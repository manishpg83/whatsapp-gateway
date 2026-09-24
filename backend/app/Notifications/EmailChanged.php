<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the OLD email address after a user changes theirs, so that if
 * someone else took over the account, the real owner finds out. The new
 * address is shown partly hidden (e.g. "ja***@example.com").
 */
class EmailChanged extends Notification
{
    use Queueable;

    public function __construct(public string $newEmail) {}

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
            ->subject('Your email address was changed')
            ->line('The email address on your '.config('app.name').' account was just changed to '.self::mask($this->newEmail).'.')
            ->line('If you made this change, you can ignore this email.')
            ->line('If you did NOT make this change, someone may have access to your account. Contact us right away so we can help you secure it.')
            ->action('Contact support', route('contact', ['topic' => 'technical']));
    }

    /**
     * "jane.doe@example.com" -> "ja***@example.com"
     */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
