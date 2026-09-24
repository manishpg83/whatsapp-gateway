<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\View\View;

/**
 * Platform-wide counts only — no per-user message content, same "counts,
 * not content" rule as Admin\UserController::show().
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $thisMonth = Message::where('created_at', '>=', now()->startOfMonth());

        // Only ACTIVE paid subscriptions count as revenue — not free, and
        // not pending/past_due/cancelled. Uses each plan's CURRENT price,
        // so it's an estimate: someone who subscribed before a price change
        // still pays the old price at Cashfree, which we don't store.
        $planBreakdown = Plan::withCount([
            'subscriptions',
            'subscriptions as active_subscriptions_count' => fn ($q) => $q->where('status', 'active'),
        ])->orderBy('price')->get();

        foreach ($planBreakdown as $plan) {
            $plan->monthly_revenue = $plan->price * $plan->active_subscriptions_count;
        }

        $paidPlanSlugs = $planBreakdown->where('price', '>', 0)->pluck('slug');

        return view('admin.dashboard', [
            'mrr' => $planBreakdown->sum('monthly_revenue'),
            'payingCustomers' => Subscription::whereIn('plan', $paidPlanSlugs)->where('status', 'active')->count(),
            'pendingPayments' => Subscription::whereIn('plan', $paidPlanSlugs)->where('status', 'pending')->count(),
            'signupsThisMonth' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'signupsLastMonth' => User::where('created_at', '>=', now()->startOfMonth()->subMonthNoOverflow())
                ->where('created_at', '<', now()->startOfMonth())->count(),
            'unverifiedUsers' => User::whereNull('email_verified_at')->count(),
            'signupsByMonth' => $this->signupsByMonth(6),

            'totalUsers' => User::count(),
            'paidUsers' => Subscription::where('plan', '!=', 'free')->count(),
            'totalInstances' => WhatsappSession::count(),
            'connectedInstances' => WhatsappSession::where('status', 'connected')->count(),
            'sentCount' => (clone $thisMonth)->where('direction', 'outgoing')->whereIn('status', Message::SENT_STATUSES)->count(),
            'failedCount' => (clone $thisMonth)->where('direction', 'outgoing')->where('status', 'failed')->count(),
            'receivedCount' => (clone $thisMonth)->where('direction', 'incoming')->count(),
            'planBreakdown' => $planBreakdown,
        ]);
    }

    /**
     * Sign-ups per calendar month, oldest first, including months with none.
     *
     * @return array<string, int> e.g. ['Apr 2026' => 3, ..., 'Sep 2026' => 5]
     */
    private function signupsByMonth(int $months): array
    {
        $result = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($i);

            $result[$start->format('M Y')] = User::where('created_at', '>=', $start)
                ->where('created_at', '<', $start->copy()->addMonthNoOverflow())
                ->count();
        }

        return $result;
    }
}
