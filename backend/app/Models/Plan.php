<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A billing tier, editable by admins (see Admin\PlanController). Replaces
 * the old config/plans.php — Subscription::planDetails() looks a row up by
 * `slug`, the same string subscriptions.plan already stored, so nothing
 * about how a subscription references its plan had to change.
 *
 * `slug`, `cashfree_plan_id` and `price_version` are deliberately NOT
 * fillable — they're derived/assigned explicitly in the controller
 * (see cashfreePlanId()), never taken directly from a form.
 */
#[Fillable(['name', 'description', 'price', 'instances', 'messages_per_month', 'popular'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'instances' => 'integer',
            'messages_per_month' => 'integer',
            'popular' => 'boolean',
            'price_version' => 'integer',
        ];
    }

    /**
     * subscriptions.plan is a plain string column holding this plan's
     * slug, not a foreign key to plans.id (see the plans migration) — so
     * this relation is keyed on slug rather than Eloquent's usual id
     * default.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan', 'slug');
    }

    /**
     * Cashfree Plan objects are immutable once created, so each distinct
     * price for a given plan gets its own id. Pure/deterministic —
     * no I/O, easy to unit test.
     */
    public static function cashfreePlanId(string $slug, int $priceVersion): string
    {
        return "{$slug}_v{$priceVersion}";
    }
}
