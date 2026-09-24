<?php

namespace App\Http\Controllers;

use App\Models\ApiRequestLog;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A standalone module (sidebar-level, not nested under one instance) — a
 * single audit trail across every instance the user owns, of real calls to
 * the public API. Never the dashboard's own "send a test message" button —
 * that has no token, so it isn't a real API call and has nothing to log.
 * See Message::apiCurlExample()/apiResponseExample().
 */
class ApiLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $selectedInstanceId = $request->query('instance_id');
        $tab = $request->query('tab') === 'rejected' ? 'rejected' : 'calls';

        // Second tab: calls that were rejected before any message was made
        // (see LogRejectedApiRequests). Same owner scoping as below.
        $rejected = ApiRequestLog::whereHas('whatsappSession', fn ($q) => $q->where('user_id', $user->id))
            ->when($selectedInstanceId, fn ($q) => $q->whereHas('whatsappSession', fn ($s) => $s->where('instance_id', $selectedInstanceId)));

        $rejectedCount = (clone $rejected)->count();

        if ($tab === 'rejected') {
            return view('api-logs.index', [
                'tab' => $tab,
                'rejectedLogs' => $rejected->with(['whatsappSession', 'apiToken'])->latest()->latest('id')->paginate(20)->withQueryString(),
                'rejectedCount' => $rejectedCount,
                'instances' => $user->whatsappSessions()->orderBy('name')->get(),
                'selectedInstanceId' => $selectedInstanceId,
            ]);
        }

        $query = Message::whereHas('whatsappSession', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('api_token_id')
            ->with(['whatsappSession', 'apiToken']);

        if ($selectedInstanceId) {
            // Scoped to $user's own messages regardless (whereHas above),
            // so an unknown/foreign instance_id just yields zero rows —
            // never another user's data, even if guessed.
            $query->whereHas('whatsappSession', fn ($q) => $q->where('instance_id', $selectedInstanceId));
        }

        return view('api-logs.index', [
            'tab' => $tab,
            'rejectedCount' => $rejectedCount,
            // latest('id') breaks ties between calls in the same second, so
            // paging never shows a row twice or skips one.
            'logs' => $query->latest()->latest('id')->paginate(20)->withQueryString(),
            'instances' => $user->whatsappSessions()->orderBy('name')->get(),
            'selectedInstanceId' => $selectedInstanceId,
        ]);
    }
}
