@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="text-center mb-4">
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center fs-3" style="width: 64px; height: 64px; background-color: var(--wa-info-light); color: var(--wa-info);">
                <i class="bi bi-envelope-check"></i>
            </span>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Check your email</h1>
                <p class="text-muted mb-2">
                    We've sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
                    Click the link in that email to activate your account.
                </p>
                <p class="text-muted small mb-4">
                    The link expires in 60 minutes. Can't find it? Check your spam folder, or send a new one.
                </p>

                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Resend verification email</button>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Wrong email address? Log out (top right) and register again.
                    Still stuck? <a href="{{ route('contact', ['topic' => 'technical']) }}">Contact us</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
