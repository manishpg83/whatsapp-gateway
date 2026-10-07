<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer who was just sent the numbered menu: which entry each number
 * meant (rule_ids, option 1 first) until expires_at. See SendChatbotReply.
 */
#[Fillable(['whatsapp_session_id', 'phone', 'rule_ids', 'expires_at'])]
class ChatbotMenuState extends Model
{
    protected function casts(): array
    {
        return [
            'rule_ids' => 'array',
            'expires_at' => 'datetime',
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
