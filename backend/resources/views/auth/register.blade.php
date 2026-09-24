@extends('layouts.app')

@section('title', 'Register')

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
                <h1 class="h4 mb-4">Create your account</h1>

                <form method="POST" action="{{ route('register') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        <div class="form-text">At least 8 characters.</div>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control" required autocomplete="new-password">
                    </div>

                    <div class="form-check mb-3">
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

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i>Register</button>
                </form>

                <p class="text-center text-muted mt-3 mb-0">
                    Already registered? <a href="{{ route('login') }}">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
