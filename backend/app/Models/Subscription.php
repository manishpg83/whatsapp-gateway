<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'plan',
    'cashfree_subscription_id',
    'status',
    'current_period_end',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The config/plans.php entry for this subscription's plan. Falls back
     * to the free plan if the stored key is somehow unrecognised (e.g. a
     * plan got renamed/removed) rather than erroring the user's account.
     *
     * @return array{name: string, price: int, instances: int, messages_per_month: int}
     */
    public function planDetails(): array
    {
        return config('plans.'.$this->plan) ?? config('plans.free');
    }
}
