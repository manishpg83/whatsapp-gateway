<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'whatsapp_session_id',
    'api_token_id',
    'chatbot_rule_id',
    'bot_reply',
    'direction',
    'type',
    'to_number',
    'from_number',
    'body',
    'media_status',
    'media_path',
    'media_mime_type',
    'media_file_name',
    'media_size',
    'status',
    'delivered_at',
    'read_at',
    'whatsapp_message_id',
    'error',
    'fallback_status',
    'fallback_message_id',
    'fallback_error',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    // An outgoing message that reached WhatsApp — later receipts only move
    // it along this list (sent → delivered → read), it's still "sent".
    // Use this, not just 'sent', whenever counting sent messages.
    public const SENT_STATUSES = ['sent', 'delivered', 'read'];

    // direction: 'incoming' (from a customer), 'outgoing' (sent through us:
    // API, bulk, chatbot, Inbox) or 'phone' (the owner typed it on their own
    // phone — shown in the Inbox only; never counted toward plans or stats).
    public const DIRECTION_PHONE = 'phone';

    protected static function booted(): void
    {
        // Keeps the Inbox conversation list up to date (its latest message).
        static::created(fn (Message $message) => InboxConversation::messageAdded($message));
    }

    protected function casts(): array
    {
        return [
            // Message text is encrypted in the database with APP_KEY (privacy:
            // unreadable in phpMyAdmin / DB backups). Losing or changing
            // APP_KEY makes every stored message unreadable.
            'body' => 'encrypted',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    // type => [label, Bootstrap icon]
    public const TYPES = [
        'text' => ['Text', 'bi-chat-left-text'],
        'image' => ['Image', 'bi-image'],
        'video' => ['Video', 'bi-camera-video'],
        'voice' => ['Voice note', 'bi-mic'],
        'audio' => ['Audio', 'bi-music-note-beamed'],
        'document' => ['Document', 'bi-file-earmark'],
        'sticker' => ['Sticker', 'bi-emoji-smile'],
        'location' => ['Location', 'bi-geo-alt'],
        'contact' => ['Contact', 'bi-person-vcard'],
        'unsupported' => ['Unsupported', 'bi-question-circle'],
    ];

    // Media types browsers may show inline (<img>, <audio>, <video>).
    // Anything else — PDFs, HTML, SVG, ... — is always served as a
    // download, never rendered on our domain (it could contain scripts).
    private const INLINE_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac',
        'video/mp4', 'video/3gpp',
    ];

    /**
     * Only messages that went through the gateway — not ones the owner
     * typed on their phone (for logs and lists outside the Inbox).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeThroughGateway(Builder $query): void
    {
        $query->where('direction', '!=', self::DIRECTION_PHONE);
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

    /**
     * How the status is shown everywhere: [Bootstrap colour, label, icon].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function statusBadge(): array
    {
        return match ($this->status) {
            'pending' => ['secondary', 'Pending', 'bi-clock'],
            'sent' => ['success', 'Sent', 'bi-check'],
            'delivered' => ['success', 'Delivered', 'bi-check-all'],
            'read' => ['info', 'Read', 'bi-check-all'],
            'failed' => ['danger', 'Failed', 'bi-x'],
            'received' => ['primary', 'Received', 'bi-arrow-down-left'],
            default => ['secondary', ucfirst((string) $this->status), 'bi-dot'],
        };
    }

    /**
     * Badge for the Cloud API fallback, or null when none was attempted.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public function fallbackBadge(): ?array
    {
        return match ($this->fallback_status) {
            'sent' => ['success', 'Sent via Cloud API', 'bi-cloud-check'],
            'failed' => ['danger', 'Cloud API failed', 'bi-cloud-slash'],
            default => null,
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst((string) $this->type);
    }

    public function typeIcon(): string
    {
        return self::TYPES[$this->type][1] ?? 'bi-question-circle';
    }

    /**
     * True only when the file really is on disk and can be served.
     */
    public function hasStoredMedia(): bool
    {
        return $this->media_status === 'stored'
            && $this->media_path !== null
            && Storage::disk('whatsapp_media')->exists($this->media_path);
    }

    /**
     * Whether the Inbox may offer "Retry": an outgoing message that failed
     * (and wasn't delivered by the Cloud API fallback instead), of a type
     * we can send, with its file still stored if it had one.
     */
    public function canBeRetried(): bool
    {
        return $this->direction === 'outgoing'
            && $this->status === 'failed'
            && $this->fallback_status !== 'sent'
            && in_array($this->type, ['text', 'image', 'video', 'audio', 'voice', 'document'], true)
            && ($this->type === 'text' || $this->hasStoredMedia());
    }

    public function mediaIsInline(): bool
    {
        return in_array($this->media_mime_type, self::INLINE_MIME_TYPES, true);
    }

    /**
     * The name a download is saved as: the sender's own file name for
     * documents, otherwise e.g. "image-3EB0ABC.jpg".
     */
    public function mediaDownloadName(): string
    {
        return $this->media_file_name
            ?: $this->type.'-'.basename((string) $this->media_path);
    }

    /**
     * e.g. "184 KB", "2.4 MB". (Laravel's Number::fileSize() needs PHP's
     * intl extension, which XAMPP doesn't enable by default.)
     */
    public function mediaSizeLabel(): ?string
    {
        if (! $this->media_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->media_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit >= 2 ? round($size, 1) : round($size)).' '.$units[$unit];
    }

    /**
     * A time-limited link (no login needed) for the customer's own server,
     * sent in webhooks. Anyone holding the link can download the file until
     * it expires — same as any other link in the webhook payload.
     */
    public function temporaryMediaUrl(int $hours = 24): string
    {
        return URL::temporarySignedRoute('media.signed', now()->addHours($hours), ['message' => $this->id]);
    }

    /**
     * Reconstructs the exact curl command this API call was equivalent to
     * — never the caller's real token (we never store the plaintext, only
     * its hash + 8-char prefix, per CLAUDE.md §6/§10: never log raw
     * tokens), so the token is always shown masked.
     */
    public function apiCurlExample(): string
    {
        $fields = [
            'instance_id' => $this->whatsappSession->instance_id,
            'to' => $this->to_number,
        ];

        if ($this->type !== 'text') {
            $fields['type'] = $this->type;
            // The caller's original URL isn't stored — only the file we fetched from it.
            $fields['media_url'] = '(original URL not stored)';
        }

        if ($this->type === 'text' || $this->body !== '') {
            $fields['message'] = $this->body;
        }

        if ($this->type === 'document' && $this->media_file_name) {
            $fields['file_name'] = $this->media_file_name;
        }

        $payload = json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

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
        if ($this->fallback_status === 'sent') {
            return ['status' => 200, 'body' => ['success' => true, 'message_id' => $this->fallback_message_id, 'sent_via' => 'cloud_api', 'fallback_status' => 'sent']];
        }

        return match ($this->status) {
            'sent', 'delivered', 'read' => ['status' => 200, 'body' => ['success' => true, 'message_id' => $this->whatsapp_message_id, 'sent_via' => 'device', 'fallback_status' => null]],
            'failed' => ['status' => 502, 'body' => ['success' => false, 'error' => 'Could not send message', 'fallback_status' => $this->fallback_status]],
            default => ['status' => 0, 'body' => ['success' => null, 'note' => 'still pending']],
        };
    }
}
