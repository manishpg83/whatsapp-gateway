<?php

namespace App\Models;

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
        ];
    }

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
     * Deletes every received media file for this instance from disk. The
     * database rows go away by cascade when a user is deleted, but files
     * don't — so account deletion (self-service and admin) calls this
     * first, keeping the Privacy Policy's "deletion removes your data" true.
     */
    public function deleteMediaFiles(): void
    {
        Storage::disk('whatsapp_media')->deleteDirectory($this->instance_id);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
