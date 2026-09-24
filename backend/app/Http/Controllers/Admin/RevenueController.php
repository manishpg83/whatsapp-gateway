<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who is paying, how much, and what's at risk — built only from the
 * subscriptions table (each user's CURRENT plan + status). There is no
 * record of payments actually received yet, so every money figure here is
 * an estimate: active paid subscriptions × each plan's current price.
 * Admin-only via the `admin` middleware; billing info only.
 */
class RevenueController extends Controller
{
    public const STATUSES = [
        'active' => 'Active',
        'pending' => 'Pending',
        'past_due' => 'Past due',
        'cancelled' => 'Cancelled',
    ];

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), self::STATUSES) ? $request->query('status') : null;
        $search = trim((string) $request->query('search'));

        $plans = Plan::withCount([
            'subscriptions as active_subscriptions_count' => fn ($q) => $q->where('status', 'active'),
        ])->orderBy('price')->get();

        $paidPlans = $plans->where('price', '>', 0)->keyBy('slug');

        foreach ($plans as $plan) {
            $plan->monthly_revenue = $plan->price * $plan->active_subscriptions_count;
        }

        $mrr = $plans->sum('monthly_revenue');

        // Every paid (non-free) subscription, in any status.
        $paid = fn () => Subscription::whereIn('plan', $paidPlans->keys());

        $query = $paid()->with('user')->latest()->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('user', fn (Builder $u) => $u
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $statusCounts = [];
        foreach (array_keys(self::STATUSES) as $value) {
            $statusCounts[$value] = $paid()->where('status', $value)->count();
        }

        return view('admin.revenue.index', [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'statusCounts' => $statusCounts,
            'plans' => $plans->where('price', '>', 0)->values(),
            'paidPlans' => $paidPlans,
            'subscriptions' => $query->paginate(25)->withQueryString(),
            'statuses' => self::STATUSES,
            'status' => $status,
            'search' => $search,
        ]);
    }
}
