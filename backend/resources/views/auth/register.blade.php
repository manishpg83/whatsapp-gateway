@extends('layouts.auth')

@section('title', 'Register')

@section('content')
<span class="lp-auth-icon"><i class="bi bi-rocket-takeoff"></i></span>
<h1 class="lp-auth-title">Create your account</h1>
<p class="lp-auth-sub">Start on the free plan. No credit card required.</p>

<form method="POST" action="{{ route('register') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="name" class="form-label">Name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Your name"
               class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@company.com"
               class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label for="password" class="form-label">Password</label>
            <x-password-input id="password" name="password" autocomplete="new-password" />
            <div class="form-text">At least 8 characters.</div>
        </div>

        <div class="col-sm-6">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
        </div>
    </div>

    <div class="form-check mb-4">
        <input type="checkbox" id="terms" name="terms" value="1" @checked(old('terms'))
               class="form-check-input @error('terms') is-invalid @enderror" required>
        <label for="terms" class="form-check-label small">
            I am at least 18 years old and agree to the
            <a href="{{ route('terms') }}" target="_blank">Terms of Service</a> and
            <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a>.
        </label>
        @error('terms')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="lp-btn lp-btn-primary">
        <i class="bi bi-person-plus"></i> Register <i class="bi bi-arrow-right lp-btn-arrow"></i>
    </button>
</form>

<p class="lp-auth-switch">
    Already registered? <a href="{{ route('login') }}">Log in</a>
</p>
@endsection
