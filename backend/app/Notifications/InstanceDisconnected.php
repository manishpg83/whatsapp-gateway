<?php

namespace App\Notifications;

use App\Models\WhatsappSession;
use App\Services\EmailTemplates;
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
        return EmailTemplates::mail('instance_disconnected', [
            'name' => $notifiable->name,
            'instance_name' => $this->instance->name,
            'phone_number' => $this->phoneNumber ?? 'no number',
            'disconnect_reason' => $this->instance->last_disconnect_reason ?? 'The connection was lost.',
            'reconnect_hint' => $this->instance->status === 'logged_out'
                ? 'The device was unlinked, so you will need to scan a new QR code.'
                : 'Your phone is still linked, so reconnecting does not need a QR code.',
        ], route('instances.show', $this->instance));
    }
}
