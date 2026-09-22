<?php

namespace App\Models;

use Database\Factories\WhatsappSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
])]
class WhatsappSession extends Model
{
    /** @use HasFactory<WhatsappSessionFactory> */
    use HasFactory;

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
}
