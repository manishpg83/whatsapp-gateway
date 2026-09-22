<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;

/**
 * Enforces config/plans.php limits. Checked before creating an instance
 * and before sending a message (both the public API and the dashboard's
 * own "send a test message" button) — never trusts the UI alone to stop
 * someone over their limit.
 */
class PlanLimiter
{
    public function instanceLimit(User $user): int
    {
        return $user->subscription->planDetails()['instances'];
    }

    public function messageLimit(User $user): int
    {
        return $user->subscription->planDetails()['messages_per_month'];
    }

    public function canCreateInstance(User $user): bool
    {
        return $user->whatsappSessions()->count() < $this->instanceLimit($user);
    }

    public function canSendMessage(User $user): bool
    {
        return $this->messagesSentThisMonth($user) < $this->messageLimit($user);
    }

    /**
     * Outgoing messages only — incoming messages aren't something the
     * user "spends" quota on, they arrive regardless of plan. This is
     * the metric the message limit is actually about.
     */
    public function messagesSentThisMonth(User $user): int
    {
        return Message::whereHas('whatsappSession', fn ($query) => $query->where('user_id', $user->id))
            ->where('direction', 'outgoing')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
