<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $instanceCount = $user->whatsappSessions()->count();
        $connectedCount = $user->whatsappSessions()->where('status', 'connected')->count();

        // All-time totals across every instance the user owns — same
        // "counts only" question the instance page and admin panel already
        // answer per-instance, just summed for a single at-a-glance number.
        $messagesForUser = Message::whereHas('whatsappSession', fn ($query) => $query->where('user_id', $user->id));
        $sentCount = (clone $messagesForUser)->where('direction', 'outgoing')->where('status', 'sent')->count();
        $receivedCount = (clone $messagesForUser)->where('direction', 'incoming')->count();

        return view('dashboard', [
            'user' => $user,
            'instanceCount' => $instanceCount,
            'connectedCount' => $connectedCount,
            'sentCount' => $sentCount,
            'receivedCount' => $receivedCount,
        ]);
    }
}
