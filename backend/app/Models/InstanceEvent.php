<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_session_id',
    'type',
    'detail',
])]
class InstanceEvent extends Model
{
    // Events never change once written, so there's no updated_at.
    public const UPDATED_AT = null;

    /**
     * type => [label, Bootstrap colour, Bootstrap icon]
     */
    public const TYPES = [
        'created' => ['Instance created', 'secondary', 'plus-circle'],
        'connected' => ['Connected', 'success', 'check-circle'],
        'connection_lost' => ['Connection lost, reconnecting automatically', 'warning', 'wifi-off'],
        'disconnected' => ['Disconnected', 'danger', 'x-circle'],
        'logged_out' => ['Logged out by WhatsApp', 'danger', 'box-arrow-right'],
        'user_disconnected' => ['Disconnected by you', 'secondary', 'pause-circle'],
        'user_logged_out' => ['Logged out by you', 'secondary', 'box-arrow-right'],
        'user_reconnect' => ['Reconnect requested', 'info', 'arrow-repeat'],
    ];

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function color(): string
    {
        return self::TYPES[$this->type][1] ?? 'secondary';
    }

    public function icon(): string
    {
        return self::TYPES[$this->type][2] ?? 'dot';
    }
}
