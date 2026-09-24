<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\Plan;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\WorkerClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * The one deliberate exception to "a user never sees another user's data"
 * (CLAUDE.md §5) — gated entirely by the `admin` middleware alias
 * (EnsureUserIsAdmin), not by anything in here. Message bodies and phone
 * numbers are never queried, only counts — see show(). Plan changes,
 * suspension and deletion are admin actions on the user's account itself,
 * not a window into their message content.
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

        // This calendar month only — matches the platform dashboard's own
        // "Messages this month" stat, so the two never disagree.
        $startOfMonth = now()->startOfMonth();

        $instances = $user->whatsappSessions()
            ->withCount([
                'messages as messages_sent_count' => fn ($query) => $query
                    ->where('direction', 'outgoing')->whereIn('status', Message::SENT_STATUSES)
                    ->where('created_at', '>=', $startOfMonth),
                'messages as messages_failed_count' => fn ($query) => $query
                    ->where('direction', 'outgoing')->where('status', 'failed')
                    ->where('created_at', '>=', $startOfMonth),
                'messages as messages_received_count' => fn ($query) => $query
                    ->where('direction', 'incoming')
                    ->where('created_at', '>=', $startOfMonth),
            ])
            ->latest()
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'instances' => $instances,
            'auditLogs' => AdminAuditLog::where('target_type', 'user')->where('target_id', $user->id)
                ->latest()->latest('id')->take(10)->get(),
            'plans' => Plan::orderBy('price')->get(),
        ]);
    }

    /**
     * Moves a user onto a different plan directly — a local override, e.g.
     * comping an account or manually downgrading one. Deliberately does
     * NOT touch Cashfree at all (no checkout, no cancellation of any real
     * subscription there) — if the user has a genuine paid Cashfree
     * subscription running, that keeps billing independently of this.
     */
    public function updatePlan(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
        ]);

        $oldPlan = $user->subscription()->value('plan');
        $user->subscription()->update(['plan' => $data['plan']]);

        AdminAudit::record($request, 'user.plan_changed', $user, ['plan' => ['from' => $oldPlan, 'to' => $data['plan']]]);

        return redirect()->route('admin.users.show', $user)
            ->with('status', "{$user->name}'s plan was changed to ".Plan::where('slug', $data['plan'])->value('name').'.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('admin.users.show', $user)->with('error', "You can't suspend your own account.");
        }

        if ($user->is_admin) {
            return redirect()->route('admin.users.show', $user)->with('error', "Can't suspend another admin account.");
        }

        // Direct property assignment, not update() — is_suspended is
        // deliberately not in User's #[Fillable(...)] list (same reasoning
        // as is_admin), so mass assignment would silently no-op here.
        $user->is_suspended = true;
        $user->save();

        AdminAudit::record($request, 'user.suspended', $user);

        return redirect()->route('admin.users.show', $user)
            ->with('status', "{$user->name}'s account has been suspended.");
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $user->is_suspended = false;
        $user->save();

        AdminAudit::record($request, 'user.unsuspended', $user);

        return redirect()->route('admin.users.show', $user)
            ->with('status', "{$user->name}'s account is no longer suspended.");
    }

    /**
     * Same guardrails as the user's own self-delete (AccountController) —
     * best-effort worker cleanup, blocked while an active paid subscription
     * exists (so Cashfree doesn't keep billing a deleted account) — plus
     * admin-specific guardrails against deleting yourself or another admin.
     */
    public function destroy(Request $request, User $user, WorkerClient $worker): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('admin.users.show', $user)->with('error', "You can't delete your own account from here.");
        }

        if ($user->is_admin) {
            return redirect()->route('admin.users.show', $user)->with('error', "Can't delete another admin account.");
        }

        $user->loadMissing('subscription');

        if ($user->subscription->plan !== 'free' && $user->subscription->status === 'active') {
            return redirect()->route('admin.users.show', $user)->with(
                'error',
                'This user has an active paid subscription. Move them to the Free plan (or cancel it at Cashfree) before deleting.'
            );
        }

        foreach ($user->whatsappSessions as $whatsappSession) {
            try {
                $worker->stopSession($whatsappSession->instance_id);
            } catch (Throwable $e) {
                Log::warning('Worker unreachable while cleaning up a session during admin user deletion', [
                    'instance_id' => $whatsappSession->instance_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $whatsappSession->deleteMediaFiles();
        }

        $name = $user->name;

        // Logged before the delete, while the user still exists — the
        // entry keeps their name/email after the account is gone.
        AdminAudit::record($request, 'user.deleted', $user, ['plan' => $user->subscription->plan]);

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "{$name}'s account has been deleted.");
    }
}
