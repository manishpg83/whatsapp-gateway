<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every message (sent and received) across every instance the user owns,
 * with optional filters. Unlike API Logs, this includes dashboard test
 * sends and incoming messages too.
 */
class MessageController extends Controller
{
    // Allowed filter values — anything else in the query string is ignored.
    private const DIRECTIONS = ['outgoing', 'incoming'];

    private const STATUSES = ['sent', 'delivered', 'read', 'failed', 'pending', 'received'];

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'instance_id' => $request->query('instance_id'),
            'direction' => in_array($request->query('direction'), self::DIRECTIONS, true) ? $request->query('direction') : null,
            'status' => in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null,
            'type' => array_key_exists((string) $request->query('type'), Message::TYPES) ? $request->query('type') : null,
        ];

        // Always scoped to $user's own instances (CLAUDE.md §5), so a guessed
        // or foreign instance_id just yields zero rows — never another user's data.
        $query = Message::whereHas('whatsappSession', fn ($q) => $q->where('user_id', $user->id))
            ->with('whatsappSession');

        if ($filters['instance_id']) {
            $query->whereHas('whatsappSession', fn ($q) => $q->where('instance_id', $filters['instance_id']));
        }

        if ($filters['direction']) {
            $query->where('direction', $filters['direction']);
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['type']) {
            $query->where('type', $filters['type']);
        }

        return view('messages.index', [
            'messages' => $query->latest()->latest('id')->paginate(20)->withQueryString(),
            'instances' => $user->whatsappSessions()->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }
}
