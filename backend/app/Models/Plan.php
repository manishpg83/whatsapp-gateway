<?php

namespace App\Models;

use App\Support\Currency;
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
#[Fillable(['name', 'description', 'price', 'price_zar', 'instances', 'messages_per_month', 'chatbot_entries', 'popular'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'price_zar' => 'integer',
            'instances' => 'integer',
            'messages_per_month' => 'integer',
            'chatbot_entries' => 'integer',
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
     * A plan's monthly price in the visitor's currency (see Currency):
     * `price` (INR) or `price_zar`. 0 = free everywhere. Null = no Rand
     * price set yet — show "Contact us".
     *
     * @param  self|array<string, mixed>  $plan  a plan, or one as an array (planDetails())
     */
    public static function localPrice(self|array $plan, ?string $currency = null): ?int
    {
        if ($plan instanceof self) {
            $plan = $plan->toArray();
        }

        if ((int) $plan['price'] === 0) {
            return 0;
        }

        return ($currency ?? Currency::current()) === Currency::ZAR
            ? (isset($plan['price_zar']) ? (int) $plan['price_zar'] : null)
            : (int) $plan['price'];
    }

    /**
     * The pricing-card line for a plan's chatbot limit.
     */
    public static function chatbotLabel(int $entries): string
    {
        return $entries > 0 ? number_format($entries).' chatbot '.($entries === 1 ? 'entry' : 'entries') : 'No chatbot';
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
