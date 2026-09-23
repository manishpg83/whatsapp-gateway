@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="text-center mb-4">
            <span class="bg-wa-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center fs-3" style="width: 64px; height: 64px;">
                <i class="bi bi-chat-dots-fill"></i>
            </span>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4">Log in</h1>

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label for="remember" class="form-check-label small">Remember me</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Log in</button>
                </form>

                <p class="text-center text-muted mt-3 mb-0">
                    No account yet? <a href="{{ route('register') }}">Register</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
