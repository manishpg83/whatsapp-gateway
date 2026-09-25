@extends('layouts.auth')

@section('title', 'Log in')

@section('content')
<span class="lp-auth-icon"><i class="bi bi-box-arrow-in-right"></i></span>
<h1 class="lp-auth-title">Log in</h1>
<p class="lp-auth-sub">Welcome back! Log in to manage your WhatsApp instances.</p>

<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@company.com"
               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <x-password-input id="password" name="password" autocomplete="current-password" placeholder="Your password" />
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label for="remember" class="form-check-label small">Remember me</label>
        </div>
        <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
    </div>

    <button type="submit" class="lp-btn lp-btn-primary">
        Log in <i class="bi bi-arrow-right lp-btn-arrow"></i>
    </button>
</form>

<p class="lp-auth-switch">
    No account yet? <a href="{{ route('register') }}">Register for free</a>
</p>
@endsection
