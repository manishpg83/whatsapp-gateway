@extends('layouts.app')

@section('title', 'Reset password')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="text-center mb-4">
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center fs-3" style="width: 64px; height: 64px; background-color: var(--wa-info-light); color: var(--wa-info);">
                <i class="bi bi-key"></i>
            </span>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4">Choose a new password</h1>

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

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Reset password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
