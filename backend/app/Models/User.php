<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WhatsappSession, $this>
     */
    public function whatsappSessions(): HasMany
    {
        return $this->hasMany(WhatsappSession::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    protected static function booted(): void
    {
        // Every user has exactly one Subscription row, starting on the
        // free plan — created automatically so nothing that creates a
        // User (registration, factories, future admin tools) has to
        // remember to also set up billing.
        static::created(function (User $user) {
            $user->subscription()->create(['plan' => 'free']);
        });
    }
}
