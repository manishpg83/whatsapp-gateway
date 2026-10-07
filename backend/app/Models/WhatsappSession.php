<?php

namespace App\Models;

use App\Support\ChatbotHours;
use Database\Factories\WhatsappSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'instance_id',
    'user_id',
    'name',
    'status',
    'qr_code',
    'qr_updated_at',
    'phone_number',
    'connected_at',
    'last_disconnect_reason',
    'webhook_url',
    'webhook_secret',
    'fallback_enabled',
    'chatbot_enabled',
    'chatbot_hours',
    'chatbot_pause_minutes',
    'cloud_phone_number_id',
    'cloud_access_token',
])]
class WhatsappSession extends Model
{
    /** @use HasFactory<WhatsappSessionFactory> */
    use HasFactory;

    public const WAITING_STATUSES = ['connecting', 'qr_pending'];

    public const STUCK_AFTER_MINUTES = 10;

    /**
     * Use the public UUID (not the internal auto-increment id) in route
     * model binding, so instance URLs can't be enumerated by guessing.
     */
    public function getRouteKeyName(): string
    {
        return 'instance_id';
    }

    protected function casts(): array
    {
        return [
            'qr_updated_at' => 'datetime',
            'connected_at' => 'datetime',
            'fallback_enabled' => 'boolean',
            'chatbot_enabled' => 'boolean',
            'chatbot_hours' => 'array',
            'chatbot_pause_minutes' => 'integer',
            // The owner's Meta access token — encrypted at rest with APP_KEY.
            'cloud_access_token' => 'encrypted',
        ];
    }

    /**
     * Never include the Meta access token if this model is ever serialised.
     *
     * @var list<string>
     */
    protected $hidden = ['cloud_access_token'];

    protected static function booted(): void
    {
        static::creating(function (WhatsappSession $session) {
            $session->instance_id ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ApiToken, $this>
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Waiting to be scanned/connected, but with no update from the worker
     * for STUCK_AFTER_MINUTES. While the worker is alive it refreshes the
     * QR (and so updated_at) every ~20 seconds, so this much silence means
     * it has stopped handling this instance — usually it isn't running.
     */
    public function isStuck(): bool
    {
        return in_array($this->status, self::WAITING_STATUSES, true)
            && $this->updated_at->lt(now()->subMinutes(self::STUCK_AFTER_MINUTES));
    }

    /**
     * The Cloud API fallback is on AND has everything it needs to send.
     */
    public function canUseFallback(): bool
    {
        return $this->fallback_enabled
            && $this->cloud_phone_number_id
            && $this->cloud_access_token;
    }

    /**
     * Deletes every received media file for this instance from disk. The
     * database rows go away by cascade when a user is deleted, but files
     * don't — so account deletion (self-service and admin) calls this
     * first, keeping the Privacy Policy's "deletion removes your data" true.
     */
    public function deleteMediaFiles(): void
    {
        Storage::disk('whatsapp_media')->deleteDirectory($this->instance_id);
    }

    public function chatbotHours(): ChatbotHours
    {
        return new ChatbotHours($this->chatbot_hours);
    }

    /**
     * Chats where the owner replied by hand, so the bot stays quiet there.
     *
     * @return HasMany<ChatbotPause, $this>
     */
    public function chatbotPauses(): HasMany
    {
        return $this->hasMany(ChatbotPause::class);
    }

    /**
     * Keyword → answer entries, in the owner's order (on a tie, the higher
     * one answers). New entries go to the bottom.
     *
     * @return HasMany<ChatbotRule, $this>
     */
    public function chatbotRules(): HasMany
    {
        return $this->hasMany(ChatbotRule::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @return HasMany<InstanceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InstanceEvent::class);
    }

    /**
     * Adds one line to this instance's connection history (see
     * InstanceEvent::TYPES).
     */
    public function logEvent(string $type, ?string $detail = null): void
    {
        $this->events()->create(['type' => $type, 'detail' => $detail]);
    }
}
