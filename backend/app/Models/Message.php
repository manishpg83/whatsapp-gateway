<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_session_id',
    'api_token_id',
    'direction',
    'to_number',
    'from_number',
    'body',
    'status',
    'whatsapp_message_id',
    'error',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

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

    /**
     * Reconstructs the exact curl command this API call was equivalent to
     * — never the caller's real token (we never store the plaintext, only
     * its hash + 8-char prefix, per CLAUDE.md §6/§10: never log raw
     * tokens), so the token is always shown masked.
     */
    public function apiCurlExample(): string
    {
        $payload = json_encode([
            'instance_id' => $this->whatsappSession->instance_id,
            'to' => $this->to_number,
            'message' => $this->body,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $tokenPreview = $this->apiToken
            ? $this->apiToken->token_prefix.str_repeat('•', 16)
            : 'YOUR_ACCESS_TOKEN';

        return 'curl -X POST '.url('/api/v1/messages/send')." \\\n"
            ."  -H \"Authorization: Bearer {$tokenPreview}\" \\\n"
            ."  -H \"Content-Type: application/json\" \\\n"
            ."  -d '{$payload}'";
    }

    /**
     * The exact JSON body Api\MessageController::send() actually returned
     * for this message — deliberately mirrors only what that controller
     * really sends back (e.g. the generic "Could not send message" on
     * failure, never $this->error, which is internal-only and was never
     * part of the real API response).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function apiResponseExample(): array
    {
        return match ($this->status) {
            'sent' => ['status' => 200, 'body' => ['success' => true, 'message_id' => $this->whatsapp_message_id]],
            'failed' => ['status' => 502, 'body' => ['success' => false, 'error' => 'Could not send message']],
            default => ['status' => 0, 'body' => ['success' => null, 'note' => 'still pending']],
        };
    }
}
