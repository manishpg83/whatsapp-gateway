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

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'paidUsers' => Subscription::where('plan', '!=', 'free')->count(),
            'totalInstances' => WhatsappSession::count(),
            'connectedInstances' => WhatsappSession::where('status', 'connected')->count(),
            'sentCount' => (clone $thisMonth)->where('direction', 'outgoing')->where('status', 'sent')->count(),
            'failedCount' => (clone $thisMonth)->where('direction', 'outgoing')->where('status', 'failed')->count(),
            'receivedCount' => (clone $thisMonth)->where('direction', 'incoming')->count(),
            'planBreakdown' => Plan::withCount('subscriptions')->orderBy('price')->get(),
        ]);
    }
}
