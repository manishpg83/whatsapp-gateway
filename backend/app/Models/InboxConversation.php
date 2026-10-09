<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One Inbox conversation (instance + customer number): how many messages
 * came in since the owner last looked, and the customer's WhatsApp profile
 * name, and its latest message (so the Inbox list doesn't have to work it
 * out from all messages). The messages themselves stay in `messages`.
 */
#[Fillable(['whatsapp_session_id', 'phone', 'name', 'custom_name', 'unread_count', 'last_message_id'])]
class InboxConversation extends Model
{
    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
        ];
    }

    /**
     * The name to show: the owner's own name for this customer, else their
     * WhatsApp profile name (null = neither — show the number).
     */
    public function displayName(): ?string
    {
        return $this->custom_name ?? $this->name;
    }

    /**
     * Any message was saved (in, out, bot, phone — called from
     * Message::booted()): it is now its conversation's latest message.
     */
    public static function messageAdded(Message $message): void
    {
        $phone = (string) ($message->direction === 'incoming' ? $message->from_number : $message->to_number);

        if ($phone === '' || strlen($phone) > 32) {
            return;
        }

        static::query()->upsert(
            [['whatsapp_session_id' => $message->whatsapp_session_id, 'phone' => $phone, 'last_message_id' => $message->id]],
            ['whatsapp_session_id', 'phone'],
            // GREATEST: if two messages are saved at once, the newer one wins.
            ['last_message_id' => DB::raw('GREATEST(COALESCE(last_message_id, 0), VALUES(last_message_id))')]
        );
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
