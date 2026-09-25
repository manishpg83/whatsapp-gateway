@extends('layouts.auth')

@section('title', 'Verify your email')

@section('content')
<span class="lp-auth-icon lp-tone-blue"><i class="bi bi-envelope-check"></i></span>
<h1 class="lp-auth-title">Check your email</h1>
<p class="lp-auth-sub mb-3">
    We've sent a verification link to <strong class="text-break" style="color: var(--lp-navy);">{{ auth()->user()->email }}</strong>.
    Click the link in that email to activate your account.
</p>

<div class="lp-note mt-0 mb-4">
    <i class="bi bi-clock-history"></i>
    <div>The link expires in 60 minutes. Can't find it? Check your spam folder, or send a new one.</div>
</div>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="lp-btn lp-btn-primary">
        <i class="bi bi-send"></i> Resend verification email
    </button>
</form>

<p class="lp-auth-switch small">
    Wrong email address? Log out (top right) and register again.
    Still stuck? <a href="{{ route('contact', ['topic' => 'technical']) }}">Contact us</a>.
</p>
@endsection
