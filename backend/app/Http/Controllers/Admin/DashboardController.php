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
        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'paidUsers' => Subscription::where('plan', '!=', 'free')->count(),
            'totalInstances' => WhatsappSession::count(),
            'connectedInstances' => WhatsappSession::where('status', 'connected')->count(),
            'messagesThisMonth' => Message::where('direction', 'outgoing')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'planBreakdown' => Plan::withCount('subscriptions')->orderBy('price')->get(),
        ]);
    }
}
