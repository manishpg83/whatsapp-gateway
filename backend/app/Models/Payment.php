<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'plan',
    'plan_name',
    'amount',
    'currency',
    'status',
    'cashfree_subscription_id',
    'cf_payment_id',
    'paid_at',
])]
class Payment extends Model
{
    /**
     * status => [label, Bootstrap colour, Bootstrap icon]
     */
    public const STATUSES = [
        'paid' => ['Paid', 'success', 'check-circle-fill'],
        'failed' => ['Failed', 'danger', 'x-circle-fill'],
        'cancelled' => ['Cancelled', 'secondary', 'dash-circle-fill'],
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function color(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function icon(): string
    {
        return self::STATUSES[$this->status][2] ?? 'dot';
    }
}
