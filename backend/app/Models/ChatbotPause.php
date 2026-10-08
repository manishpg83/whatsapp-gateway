<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The chatbot is quiet in this chat until paused_until, because the owner
 * replied to the customer themselves (see SendChatbotReply), or turned the
 * bot off for this chat in the Inbox (a pause of OFF_YEARS).
 */
#[Fillable(['whatsapp_session_id', 'phone', 'paused_until'])]
class ChatbotPause extends Model
{
    // "Bot off for this chat" in the Inbox. Not forever: paused_until is a
    // MySQL TIMESTAMP, which ends in 2038.
    public const OFF_YEARS = 5;

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
     * Keep the bot quiet in this chat for at least $minutes from now. Only
     * ever moves a pause later — a reply never shortens a longer pause (or
     * turns the bot back on where the owner turned it off).
     */
    public static function extend(WhatsappSession $session, string $phone, int $minutes): void
    {
        $until = now()->addMinutes($minutes);
        $pause = $session->chatbotPauses()->firstOrNew(['phone' => $phone]);

        if (! $pause->exists || $pause->paused_until->lt($until)) {
            $pause->fill(['paused_until' => $until])->save();
        }
    }

    /**
     * Turned off for this chat in the Inbox, not just paused for a while.
     */
    public function isTurnedOff(): bool
    {
        return $this->paused_until->gt(now()->addYear());
    }

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }
}
