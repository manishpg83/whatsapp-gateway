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
     * The Plan row matching this subscription's plan slug. Falls back to
     * the free plan if the stored slug is somehow unrecognised (e.g. an
     * admin deleted the plan this subscription still references) rather
     * than erroring the user's account.
     *
     * @return array{name: string, price: int, instances: int, messages_per_month: int}
     */
    public function planDetails(): array
    {
        $plan = Plan::where('slug', $this->plan)->first() ?? Plan::where('slug', 'free')->first();

        return $plan->toArray();
    }
}
