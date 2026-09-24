<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every instance on the platform, for spotting problems (disconnected,
 * logged out, stuck). Read-only, and admin-only via the `admin` middleware
 * — same "account-level info, never message content" rule as
 * Admin\UserController: no message text, tokens or webhook secrets here.
 */
class InstanceController extends Controller
{
    // Filter value => label, in the order the counters are shown.
    public const FILTERS = [
        'connected' => 'Connected',
        'waiting' => 'Waiting for QR',
        'stuck' => 'Stuck',
        'disconnected' => 'Disconnected',
        'logged_out' => 'Logged out',
    ];

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), self::FILTERS) ? $request->query('status') : null;
        $search = trim((string) $request->query('search'));

        $query = WhatsappSession::with('user')->latest()->latest('id');

        if ($status) {
            $this->applyFilter($query, $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $counts = [];
        foreach (array_keys(self::FILTERS) as $filter) {
            $counts[$filter] = $this->applyFilter(WhatsappSession::query(), $filter)->count();
        }

        return view('admin.instances.index', [
            'instances' => $query->paginate(25)->withQueryString(),
            'counts' => $counts,
            'filters' => self::FILTERS,
            'status' => $status,
            'search' => $search,
        ]);
    }

    // Same rule as WhatsappSession::isStuck(), as a database query.
    private function applyFilter(Builder $query, string $filter): Builder
    {
        $stuckBefore = now()->subMinutes(WhatsappSession::STUCK_AFTER_MINUTES);

        return match ($filter) {
            'waiting' => $query->whereIn('status', WhatsappSession::WAITING_STATUSES)->where('updated_at', '>=', $stuckBefore),
            'stuck' => $query->whereIn('status', WhatsappSession::WAITING_STATUSES)->where('updated_at', '<', $stuckBefore),
            default => $query->where('status', $filter),
        };
    }
}
