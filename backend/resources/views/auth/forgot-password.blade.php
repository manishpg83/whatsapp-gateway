@extends('layouts.app')

@section('title', 'Forgot password')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Forgot your password?</h1>
                <p class="text-muted small mb-4">Enter your email and we'll send you a link to reset it.</p>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Send reset link</button>
                </form>

                <p class="text-center text-muted mt-3 mb-0">
                    <a href="{{ route('login') }}">Back to login</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
