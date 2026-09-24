<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only list of admin actions. There is deliberately no edit/delete
 * here — the log is append-only.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = array_key_exists((string) $request->query('action'), AdminAuditLog::ACTIONS) ? $request->query('action') : null;
        $search = trim((string) $request->query('search'));

        $query = AdminAuditLog::latest()->latest('id');

        if ($action) {
            $query->where('action', $action);
        }

        if ($search !== '') {
            $query->where(fn (Builder $q) => $q
                ->where('admin_name', 'like', "%{$search}%")
                ->orWhere('target_label', 'like', "%{$search}%"));
        }

        $logs = $query->paginate(25)->withQueryString();

        // One query to find which target users still exist (deleted ones
        // are shown as plain text instead of a link).
        $existingUserIds = User::whereIn('id', $logs->where('target_type', 'user')->pluck('target_id'))->pluck('id');

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'existingUserIds' => $existingUserIds,
            'actions' => AdminAuditLog::ACTIONS,
            'action' => $action,
            'search' => $search,
        ]);
    }
}
