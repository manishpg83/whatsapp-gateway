@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
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
                        <div class="text-end mt-1">
                            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Log in</button>
                </form>

                <p class="text-center text-muted mt-3 mb-0">
                    No account yet? <a href="{{ route('register') }}">Register</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
