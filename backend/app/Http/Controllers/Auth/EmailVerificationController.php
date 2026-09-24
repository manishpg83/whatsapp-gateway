<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    // Admins go to the admin panel, everyone else to /dashboard.
    private function home(Request $request): string
    {
        return $request->user()->is_admin ? route('admin.dashboard') : route('dashboard');
    }
}
