<?php

namespace App\Models;

use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'whatsapp_session_id',
    'name',
    'token_hash',
    'token_prefix',
    'last_used_at',
    'revoked_at',
])]
class ApiToken extends Model
{
    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    /**
     * Creates a new token for an instance. The plaintext is returned ONLY
     * here, for a one-time display to the owner — nothing else in the app
     * ever sees or stores it (CLAUDE.md §6/§10: never log or store raw
     * tokens).
     *
     * @return array{token: ApiToken, plainText: string}
     */
    public static function generateFor(WhatsappSession $session, string $name): array
    {
        $plainText = Str::random(32);

        $token = static::create([
            'whatsapp_session_id' => $session->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainText),
            'token_prefix' => substr($plainText, 0, 8),
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }
}
