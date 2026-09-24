<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_session_id',
    'event',
    'url',
    'status',
    'attempts',
    'response_status',
    'error',
])]
class WebhookDelivery extends Model
{
    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    /**
     * Human label for the instance page. "pending" means two different
     * things depending on whether an attempt has happened yet.
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'success' => 'Delivered',
            $this->status === 'failed' => 'Failed',
            $this->attempts > 0 => 'Retrying',
            default => 'Queued',
        };
    }
}
