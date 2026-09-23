<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * The one deliberate exception to "a user never sees another user's data"
 * (CLAUDE.md §5) — gated entirely by the `admin` middleware alias
 * (EnsureUserIsAdmin), not by anything in here. Read-only: no edit,
 * impersonate, or delete actions in this first version. Message bodies
 * and phone numbers are never queried, only counts — see show().
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('subscription')
            ->withCount('whatsappSessions')
            ->latest()
            ->get();

        return view('admin.users.index', ['users' => $users]);
    }

    public function show(User $user): View
    {
        $user->load('subscription');

        $instances = $user->whatsappSessions()
            ->withCount([
                'messages as messages_sent_count' => fn ($query) => $query
                    ->where('direction', 'outgoing')->where('status', 'sent'),
                'messages as messages_failed_count' => fn ($query) => $query
                    ->where('direction', 'outgoing')->where('status', 'failed'),
                'messages as messages_received_count' => fn ($query) => $query
                    ->where('direction', 'incoming'),
            ])
            ->latest()
            ->get();

        return view('admin.users.show', ['user' => $user, 'instances' => $instances]);
    }
}
