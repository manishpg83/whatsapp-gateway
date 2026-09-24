<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    // Show the registration form.
    public function create(): View
    {
        return view('auth.register');
    }

    // Validate the form, create the user, log them in.
    public function store(Request $request): RedirectResponse
    {
        // Emails are case-insensitive in practice, so store them lower-case.
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Please confirm you are 18 or older and agree to the Terms of Service and Privacy Policy.',
        ]);

        unset($data['terms']); // not a users column

        // The User model hashes the password automatically (see casts()).
        $user = User::create($data);

        // Laravel listens for this event and emails the verification link.
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate(); // new session id after login (prevents session fixation)

        return redirect()->route('verification.notice');
    }
}
