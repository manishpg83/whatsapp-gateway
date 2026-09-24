<?php

namespace App\Http\Controllers;

use App\Notifications\EmailChanged;
use App\Services\WorkerClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    /**
     * Updates the name and/or email. Changing the EMAIL is treated as
     * sensitive, because it's what you log in and reset your password with:
     * - it needs the current password (a stolen logged-in session alone
     *   can't take the account over);
     * - the new address must be verified again — the user goes straight to
     *   "Check your email" and can't use the app until they click the link;
     * - the OLD address gets a "your email was changed" notice.
     * Changing only the name needs none of that.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Emails are stored lower-case (same as registration).
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $emailChanged = $request->input('email') !== $user->email;

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password'],
        ], [
            'current_password.required' => 'Enter your current password to change your email address.',
        ]);

        if (! $emailChanged) {
            $user->update(['name' => $data['name']]);

            return redirect()->route('account.edit')->with('status', 'Profile updated.');
        }

        $oldEmail = $user->email;

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();

        // A reset link sent to the old address shouldn't stay usable.
        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

        $user->sendEmailVerificationNotification();
        Notification::route('mail', $oldEmail)->notify(new EmailChanged($data['email']));

        return redirect()->route('verification.notice')
            ->with('status', 'Your email address was changed. Please verify the new address to keep using your account.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('account.edit')->with('status', 'Password updated.');
    }

    /**
     * Deletes the account. Best-effort logs out every connected WhatsApp
     * instance at the worker first (so devices don't stay linked to a
     * WhatsApp account nobody can manage from our side anymore); the
     * account row itself cascades to whatsapp_sessions/api_tokens/
     * messages/subscription via their FK constraints regardless of
     * whether the worker cleanup succeeds.
     *
     * Blocks deletion while an active paid subscription exists — we
     * don't yet have self-serve cancellation (CLAUDE.md-style known gap,
     * documented in progress.md), and silently deleting the account would
     * leave Cashfree trying to bill a card for an account that no longer
     * exists here.
     */
    public function destroy(Request $request, WorkerClient $worker): RedirectResponse
    {
        // Named error bag — this page also has a change-password form with
        // its own current_password field; without this, an error from
        // either form would show on both.
        $request->validateWithBag('deleteAccount', [
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->subscription->plan !== 'free' && $user->subscription->status === 'active') {
            return back()->with(
                'error',
                'You have an active paid subscription. Please contact support to cancel it before deleting your account.'
            );
        }

        foreach ($user->whatsappSessions as $whatsappSession) {
            try {
                $worker->stopSession($whatsappSession->instance_id);
            } catch (Throwable $e) {
                Log::warning('Worker unreachable while cleaning up a session during account deletion', [
                    'instance_id' => $whatsappSession->instance_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $whatsappSession->deleteMediaFiles();
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account has been deleted.');
    }
}
