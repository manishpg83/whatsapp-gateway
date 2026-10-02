<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One number in a bulk campaign. `message_id` points at the real message
 * once it has been sent (for its delivered/read ticks).
 */
#[Fillable(['bulk_campaign_id', 'phone', 'name', 'status', 'message_id', 'error', 'processed_at'])]
class BulkCampaignRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BulkCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BulkCampaign::class, 'bulk_campaign_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
