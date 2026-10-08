<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Inbox conversation (instance + customer number): how many messages
 * came in since the owner last looked, and the customer's WhatsApp profile
 * name. The messages themselves stay in `messages`.
 */
#[Fillable(['whatsapp_session_id', 'phone', 'name', 'unread_count'])]
class InboxConversation extends Model
{
    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
        ];
    }

    /**
     * A customer message arrived: one more unread, and their latest
     * profile name (kept when a message comes without one).
     */
    public static function messageReceived(WhatsappSession $session, string $phone, ?string $name = null): void
    {
        $conversation = static::query()->createOrFirst(['whatsapp_session_id' => $session->id, 'phone' => $phone]);

        $conversation->increment('unread_count', 1, $name !== null && $name !== '' ? ['name' => mb_substr($name, 0, 100)] : []);
    }

    /**
     * The owner opened (or replied in) the chat.
     */
    public static function markRead(WhatsappSession $session, string $phone): void
    {
        static::query()
            ->where('whatsapp_session_id', $session->id)
            ->where('phone', $phone)
            ->where('unread_count', '>', 0)
            ->update(['unread_count' => 0]);
    }

    /**
     * Unread messages across all of a user's instances (the sidebar badge).
     */
    public static function unreadFor(User $user): int
    {
        return (int) static::query()
            ->whereIn('whatsapp_session_id', $user->whatsappSessions()->select('id'))
            ->sum('unread_count');
    }

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }
}
