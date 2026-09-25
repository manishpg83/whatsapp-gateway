@extends('layouts.auth')

@section('title', 'Reset password')

@section('content')
<span class="lp-auth-icon lp-tone-blue"><i class="bi bi-shield-lock"></i></span>
<h1 class="lp-auth-title">Choose a new password</h1>
<p class="lp-auth-sub">Enter your email and a new password (at least 8 characters).</p>

<form method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">New password</label>
        <x-password-input id="password" name="password" autocomplete="new-password" />
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Confirm new password</label>
        <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
    </div>

    <button type="submit" class="lp-btn lp-btn-primary">
        <i class="bi bi-check-lg"></i> Reset password
    </button>
</form>

<p class="lp-auth-switch">
    <a href="{{ route('login') }}"><i class="bi bi-arrow-left me-1"></i>Back to login</a>
</p>
@endsection
