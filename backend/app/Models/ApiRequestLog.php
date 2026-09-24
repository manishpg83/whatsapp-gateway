<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rejected public-API call. Written only by App\Http\Middleware\LogRejectedApiRequests.
 */
#[Fillable([
    'whatsapp_session_id',
    'api_token_id',
    'method',
    'path',
    'status_code',
    'error',
    'to_number',
    'type',
    'ip_address',
])]
class ApiRequestLog extends Model
{
    // Only created_at — a log entry is never updated.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }
}
