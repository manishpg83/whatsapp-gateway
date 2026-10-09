<?php

namespace App\Models;

use App\Jobs\SendBulkMessage;
use App\Jobs\StartScheduledBulkCampaign;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One bulk message sent to many numbers. Sending is done by
 * App\Jobs\SendBulkMessage, one recipient at a time.
 */
#[Fillable([
    'campaign_id',
    'user_id',
    'whatsapp_session_id',
    'name',
    'type',
    'body',
    'name_fallback',
    'media_path',
    'media_mime_type',
    'media_file_name',
    'media_size',
    'interval_seconds',
    'status',
    'pause_reason',
    'run_token',
    'run_started_at',
    'started_at',
    'finished_at',
    'scheduled_at',
    'timezone',
])]
class BulkCampaign extends Model
{
    // status => [label, Bootstrap colour, icon]
    public const STATUSES = [
        'scheduled' => ['Scheduled', 'info', 'bi-calendar-event'],
        'running' => ['Sending', 'primary', 'bi-send'],
        'paused' => ['Paused', 'warning', 'bi-pause-circle'],
        'completed' => ['Completed', 'success', 'bi-check-circle'],
        'cancelled' => ['Cancelled', 'secondary', 'bi-x-circle'],
    ];

    // What a campaign can send: type => [label, Bootstrap icon].
    public const TYPES = [
        'text' => ['Text', 'bi-chat-left-text'],
        'image' => ['Image', 'bi-image'],
        'video' => ['Video', 'bi-camera-video'],
        'document' => ['Document', 'bi-file-earmark'],
    ];

    /**
     * Use the public UUID in URLs, so campaigns can't be enumerated.
     */
    public function getRouteKeyName(): string
    {
        return 'campaign_id';
    }

    protected function casts(): array
    {
        return [
            'body' => 'encrypted', // privacy: see CLAUDE.md §17
            'run_started_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BulkCampaign $campaign) {
            $campaign->campaign_id ??= (string) Str::uuid();
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
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    /**
     * @return HasMany<BulkCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BulkCampaignRecipient::class);
    }

    /**
     * Start or resume sending. The first message goes out once the
     * interval since the last send has passed (straight away for a new
     * campaign), so pause → resume can't send two messages back to back.
     */
    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'pause_reason' => null,
            'run_token' => (string) Str::uuid(),
            'run_started_at' => now(),
            'started_at' => $this->started_at ?? now(),
        ]);

        $lastSent = $this->recipients()->max('processed_at');
        $wait = $lastSent
            ? max(0, $this->interval_seconds - (int) now()->diffInSeconds($lastSent, absolute: true))
            : 0;

        SendBulkMessage::dispatch($this->id, $this->run_token)->delay(now()->addSeconds($wait));
    }

    /**
     * $reason is set when the system paused it (shown to the owner); a
     * pause by the owner has none.
     */
    public function pause(?string $reason = null): void
    {
        $this->update(['status' => 'paused', 'pause_reason' => $reason, 'run_token' => null]);
    }

    /**
     * Stop for good: numbers not sent yet are marked "skipped".
     */
    public function cancel(): void
    {
        $this->recipients()->where('status', 'pending')->update(['status' => 'skipped', 'processed_at' => now()]);
        $this->update(['status' => 'cancelled', 'pause_reason' => null, 'run_token' => null, 'finished_at' => now()]);
    }

    public function finish(): void
    {
        $this->update(['status' => 'completed', 'pause_reason' => null, 'run_token' => null, 'finished_at' => now()]);
    }

    /**
     * The message (or caption) for one recipient: {name} replaced with
     * their name, or name_fallback when they have none. If that is empty
     * too, the gap is tidied ("Hi {name}, ..." → "Hi, ...").
     */
    public function bodyFor(?string $name): string
    {
        $body = (string) $this->body;

        if (! str_contains($body, '{name}')) {
            return $body;
        }

        $value = trim((string) ($name ?: $this->name_fallback));
        $text = str_replace('{name}', $value, $body);

        if ($value === '') {
            $text = preg_replace('/[ \t]+([,.!?])/u', '$1', $text);
            $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
        }

        return $text;
    }

    /**
     * The stored file in the shape MessageSender::send() expects, or null
     * for a text campaign.
     *
     * @return array{path: string, mime_type: string, file_name: string|null, size: int}|null
     */
    public function media(): ?array
    {
        if ($this->type === 'text' || ! $this->media_path) {
            return null;
        }

        return [
            'path' => $this->media_path,
            'mime_type' => $this->media_mime_type,
            'file_name' => $this->media_file_name,
            'size' => (int) $this->media_size,
        ];
    }

    /**
     * Images and videos may be shown in the browser; documents are
     * always downloaded (same rule as Message::mediaIsInline()).
     */
    public function mediaIsInline(): bool
    {
        return in_array($this->type, ['image', 'video'], true);
    }

    /**
     * Running or paused — not finished yet.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['scheduled', 'running', 'paused'], true);
    }

    /**
     * Start automatically at $at (a delayed queue job — no cron needed).
     * "Send now" or "Cancel" before then replace the run_token, so this
     * queued start then does nothing.
     */
    public function schedule(Carbon $at, string $timezone): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $at,
            'timezone' => $timezone,
            'run_token' => (string) Str::uuid(),
        ]);

        StartScheduledBulkCampaign::dispatch($this->id, $this->run_token)->delay($at);
    }

    /**
     * The scheduled time in the timezone the user picked it in.
     */
    public function scheduledAtLocal(): ?Carbon
    {
        return $this->scheduled_at?->copy()->timezone($this->timezone ?: config('bulk.default_timezone'));
    }

    /**
     * Another campaign already sending from the same instance (one at a
     * time per instance, so the pacing really holds).
     */
    public function otherRunningOnInstance(): bool
    {
        return self::where('whatsapp_session_id', $this->whatsapp_session_id)
            ->where('status', 'running')
            ->whereKeyNot($this->id)
            ->exists();
    }

    /**
     * Recipient counts by status, plus total and done.
     *
     * @return array{pending: int, sent: int, failed: int, skipped: int, total: int, done: int}
     */
    public function counts(): array
    {
        $byStatus = $this->recipients()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $counts = [];
        foreach (['pending', 'sent', 'failed', 'skipped'] as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }
        $counts['total'] = array_sum($counts);
        $counts['done'] = $counts['total'] - $counts['pending'];

        return $counts;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [label, colour, icon]
     */
    public function statusBadge(): array
    {
        return self::STATUSES[$this->status] ?? [ucfirst($this->status), 'secondary', 'bi-circle'];
    }
}
