@extends('layouts.app')

@section('title', 'Account')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h1 class="h3 mb-4">Account</h1>

        {{-- Account details --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Name</div>
                <div class="mb-3">{{ $user->name }}</div>
                <div class="text-muted small text-uppercase">Email</div>
                <div>{{ $user->email }}</div>
            </div>
        </div>

        {{-- Change password --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Change password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">Update password</button>
                </form>
            </div>
        </div>

        {{-- Delete account --}}
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-white text-danger fw-semibold">Delete account</div>
            <div class="card-body">
                <p class="text-muted small">This permanently deletes your account, all your WhatsApp instances, API tokens, and message history. This cannot be undone.</p>

                <form method="POST" action="{{ route('account.destroy') }}"
                      onsubmit="return confirm('Delete your account permanently? This cannot be undone.');">
                    @csrf
                    @method('DELETE')

                    <div class="mb-3">
                        <label for="delete_current_password" class="form-label">Confirm your password</label>
                        <input type="password" id="delete_current_password" name="current_password"
                               class="form-control @error('current_password', 'deleteAccount') is-invalid @enderror" required autocomplete="current-password">
                        @error('current_password', 'deleteAccount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-outline-danger">Delete my account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
