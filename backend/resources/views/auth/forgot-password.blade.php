@extends('layouts.auth')

@section('title', 'Forgot password')

@section('content')
{{-- The "link sent" status message is shown by layouts/auth.blade.php. --}}
<span class="lp-auth-icon lp-tone-blue"><i class="bi bi-key"></i></span>
<h1 class="lp-auth-title">Forgot your password?</h1>
<p class="lp-auth-sub">Enter your email and we'll send you a link to reset it.</p>

<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf

    <div class="mb-4">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@company.com"
               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="lp-btn lp-btn-primary">
        <i class="bi bi-send"></i> Send reset link
    </button>
</form>

<p class="lp-auth-switch">
    <a href="{{ route('login') }}"><i class="bi bi-arrow-left me-1"></i>Back to login</a>
</p>
@endsection
