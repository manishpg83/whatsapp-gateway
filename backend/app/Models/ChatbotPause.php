<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The chatbot is quiet in this chat until paused_until, because the owner
 * replied to the customer from their own phone (see SendChatbotReply).
 */
#[Fillable(['whatsapp_session_id', 'phone', 'paused_until'])]
class ChatbotPause extends Model
{
    // Choices for "pause for how long" (minutes => label). 0 = never pause.
    public const DURATIONS = [
        0 => "Don't pause",
        15 => '15 minutes',
        30 => '30 minutes',
        60 => '1 hour',
        120 => '2 hours',
        240 => '4 hours',
        1440 => '24 hours',
    ];

    protected function casts(): array
    {
        return [
            'paused_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }
}
