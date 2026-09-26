<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UsageLimitWarning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails the account owner when their outgoing messages this month reach
 * 80% and then 100% of their plan's limit — each at most once per month.
 *
 * "Already sent" is remembered in the cache (no extra table): the key
 * holds the month AND the limit, so the warnings start fresh every month,
 * and again after an upgrade mid-month (a new, higher limit). Cache::add()
 * only succeeds for the first caller, so two sends at the same moment
 * can't both email.
 */
class UsageWarner
{
    public const THRESHOLDS = [100, 80]; // highest first

    public function __construct(protected PlanLimiter $limiter) {}

    public function check(User $user): void
    {
        $limit = $this->limiter->messageLimit($user);
        if ($limit <= 0) {
            return;
        }

        $used = $this->limiter->messagesSentThisMonth($user);

        foreach (self::THRESHOLDS as $percent) {
            if ($used * 100 < $limit * $percent) {
                continue;
            }

            // Only the highest threshold reached gets an email (jumping
            // straight past 80% to 100% sends just the 100% one); the
            // lower ones are marked as done too.
            $isNew = Cache::add($this->key($user, $limit, $percent), true, now()->addMonthsNoOverflow(2)->startOfMonth());
            foreach (self::THRESHOLDS as $lower) {
                if ($lower < $percent) {
                    Cache::add($this->key($user, $limit, $lower), true, now()->addMonthsNoOverflow(2)->startOfMonth());
                }
            }

            if ($isNew) {
                $this->notify($user, $percent, $used, $limit);
            }

            return;
        }
    }

    private function key(User $user, int $limit, int $percent): string
    {
        return "usage-warning:{$user->id}:".now()->format('Y-m').":{$limit}:{$percent}";
    }

    private function notify(User $user, int $percent, int $used, int $limit): void
    {
        try {
            $user->notify(new UsageLimitWarning($percent, $used, $limit, $user->subscription->planDetails()['name']));
        } catch (Throwable $e) {
            // A mail problem must never break the message send that
            // triggered this check. No address or token in the log.
            Log::warning('Could not send usage warning email', [
                'user_id' => $user->id,
                'percent' => $percent,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
