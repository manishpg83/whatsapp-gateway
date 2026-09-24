@extends('layouts.app')

@section('title', 'Account')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h1 class="h3 mb-4">Account</h1>

        {{-- Profile --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <span class="avatar-badge flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.1rem;">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div style="min-width: 0;">
                    <div class="fw-semibold text-break">{{ $user->name }}</div>
                    <div class="text-muted small text-break">{{ $user->email }}</div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.profile.update') }}" data-profile-form>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="profile_name" class="form-label">Name</label>
                        <input type="text" id="profile_name" name="name" value="{{ old('name', $user->name) }}"
                               class="form-control @error('name', 'profile') is-invalid @enderror" required maxlength="255" autocomplete="name">
                        @error('name', 'profile')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="profile_email" class="form-label">Email</label>
                        <input type="email" id="profile_email" name="email" value="{{ old('email', $user->email) }}"
                               data-original-email="{{ $user->email }}"
                               class="form-control @error('email', 'profile') is-invalid @enderror" required maxlength="255" autocomplete="email">
                        @error('email', 'profile')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Only needed (and only shown) when the email is being changed. --}}
                    <div class="mb-3" data-email-change-fields
                         @unless ($errors->profile->has('current_password') || old('email', $user->email) !== $user->email) hidden @endunless>
                        <div class="alert alert-info small py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Changing your email: we'll send a verification link to the new address, and you'll need to
                            click it before you can keep using your account. Your old address gets a notice too.
                        </div>
                        <label for="profile_current_password" class="form-label">Current password</label>
                        <x-password-input id="profile_current_password" name="current_password" autocomplete="current-password" bag="profile" :required="false" />
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save profile</button>
                </form>
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
                        <x-password-input id="current_password" name="current_password" autocomplete="current-password" />
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <x-password-input id="password" name="password" autocomplete="new-password" />
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
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
                        <x-password-input id="delete_current_password" name="current_password" autocomplete="current-password" bag="deleteAccount" />
                    </div>

                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Delete my account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Show the "current password" field only once the email is actually changed.
(function () {
    const email = document.getElementById('profile_email');
    const extra = document.querySelector('[data-email-change-fields]');

    email.addEventListener('input', () => {
        extra.hidden = email.value.trim().toLowerCase() === email.dataset.originalEmail;
    });
})();
</script>
@endsection
