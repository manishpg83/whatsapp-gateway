<?php

namespace App\Notifications;

use App\Models\WhatsappSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the instance owner when a linked instance goes offline without
 * them doing it (unlinked from the phone, or automatic reconnecting gave
 * up) — otherwise their API sends would quietly start failing.
 */
class InstanceDisconnected extends Notification
{
    use Queueable;

    public function __construct(public WhatsappSession $instance, public ?string $phoneNumber) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loggedOut = $this->instance->status === 'logged_out';

        return (new MailMessage)
            ->subject("WhatsApp instance \"{$this->instance->name}\" is offline")
            ->line("Your WhatsApp instance \"{$this->instance->name}\"".($this->phoneNumber ? " ({$this->phoneNumber})" : '').' is no longer connected, so messages sent through the API will fail until it is back online.')
            ->line($this->instance->last_disconnect_reason ?? 'The connection was lost.')
            ->line($loggedOut
                ? 'The device was unlinked, so you will need to scan a new QR code.'
                : 'Your phone is still linked, so reconnecting does not need a QR code.')
            ->action('Open instance', route('instances.show', $this->instance));
    }
}
