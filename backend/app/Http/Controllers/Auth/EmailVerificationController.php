<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Laravel's built-in email verification (User implements MustVerifyEmail).
 * New users can't reach any logged-in page until they click the signed
 * link we email them (the `verified` middleware sends them here instead).
 */
class EmailVerificationController extends Controller
{
    // "Check your email" page.
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($this->home($request));
        }

        return view('auth.verify-email');
    }

    // The link from the email. EmailVerificationRequest checks the link is
    // signed, not expired, and belongs to the logged-in user — otherwise 403.
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        $this->sendWelcomeOnce($request->user());

        return redirect()->to($this->home($request))->with('status', 'Thanks — your email is verified.');
    }

    // "Resend email" button.
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($this->home($request));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent to your email.');
    }

    // The welcome email goes out the first time an account is verified —
    // never again (e.g. after an email change, which needs re-verifying),
    // and never to admin accounts. The conditional update claims it, so a
    // double-clicked link can't send two.
    private function sendWelcomeOnce(User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $claimed = User::whereKey($user->id)->whereNull('welcome_sent_at')->update(['welcome_sent_at' => now()]);
        if (! $claimed) {
            return;
        }

        try {
            $user->notify(new WelcomeUser);
        } catch (Throwable $e) {
            // A mail problem mustn't break verification — they still land
            // on their dashboard. No email address in the log.
            Log::warning('Could not send welcome email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    // Admins go to the admin panel, everyone else to /dashboard.
    private function home(Request $request): string
    {
        return $request->user()->is_admin ? route('admin.dashboard') : route('dashboard');
    }
}
