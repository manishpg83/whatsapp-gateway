@extends('layouts.app')

@section('title', 'Account')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h1 class="h3 mb-4">Account</h1>

        {{-- Account details --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="avatar-badge flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.1rem;">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div>
                    <div class="fw-semibold">{{ $user->name }}</div>
                    <div class="text-muted small">{{ $user->email }}</div>
                </div>
            </div>
        </div>

        {{-- Change password --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div>
                    <div class="fw-semibold">Change password</div>
                    <div class="text-muted small">Choose a new password for your account.</div>
                </div>
            </div>
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

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update password</button>
                </form>
            </div>
        </div>

        {{-- Delete account --}}
        <div class="card shadow-sm border-danger">
            <div class="card-body d-flex align-items-center gap-3 border-bottom border-danger-subtle">
                <div class="rounded-circle p-2 fs-4 lh-1 bg-danger-subtle text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="fw-semibold text-danger">Delete account</div>
                    <div class="text-muted small">This permanently deletes your account, all your WhatsApp instances, API tokens, and message history. This cannot be undone.</div>
                </div>
            </div>
            <div class="card-body">
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

                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Delete my account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
