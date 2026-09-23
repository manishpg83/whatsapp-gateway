@extends('layouts.app')

@section('title', 'Admin · ' . $user->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
        <span class="avatar-badge" style="width: 40px; height: 40px; font-size: 1.1rem;">
            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
        </span>
        <div>
            <h1 class="h4 mb-0 d-flex align-items-center gap-2">
                {{ $user->name }}
                @if ($user->is_admin)
                    <span class="badge text-bg-secondary">Admin</span>
                @endif
                @if ($user->is_suspended)
                    <span class="badge text-bg-danger">Suspended</span>
                @endif
            </h1>
            <div class="text-muted small">{{ $user->email }}</div>
        </div>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to users
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Plan</div>
                <div class="h5 mb-0">{{ $user->subscription->planDetails()['name'] }}</div>
                <div class="text-muted small">Status: {{ ucfirst(str_replace('_', ' ', $user->subscription->status)) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Instances</div>
                <div class="display-6 fw-semibold">{{ $instances->count() }}</div>
                <div class="text-muted small">{{ $instances->where('status', 'connected')->count() }} connected</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--wa-primary-dark);">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Messages</div>
                <div class="text-muted small">
                    <i class="bi bi-arrow-up-short text-primary"></i>{{ $instances->sum('messages_sent_count') }} sent
                </div>
                <div class="text-muted small">
                    <i class="bi bi-arrow-down-short text-primary"></i>{{ $instances->sum('messages_received_count') }} received
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-blue shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Joined</div>
                <div class="h5 mb-0">{{ $user->created_at->format('M j, Y') }}</div>
                <div class="text-muted small">{{ $user->created_at->diffForHumans() }}</div>
            </div>
        </div>
    </div>
</div>

@unless ($user->is_admin || $user->id === auth()->id())
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex align-items-center gap-3 border-bottom">
            <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
                <i class="bi bi-gear"></i>
            </div>
            <div class="fw-semibold">Manage account</div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="small text-muted text-uppercase mb-2">Plan</div>
                    <form method="POST" action="{{ route('admin.users.plan.update', $user) }}" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <select name="plan" class="form-select form-select-sm">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->slug }}" @selected($user->subscription->plan === $plan->slug)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Change</button>
                    </form>
                    <div class="form-text">Local override only — does not touch any real Cashfree subscription.</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted text-uppercase mb-2">Login access</div>
                    @if ($user->is_suspended)
                        <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-check-circle me-1"></i>Unsuspend
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}"
                              onsubmit="return confirm('Suspend {{ $user->name }}? They will be logged out and unable to log back in until unsuspended.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-slash-circle me-1"></i>Suspend
                            </button>
                        </form>
                    @endif
                </div>
                <div class="col-md-3">
                    <div class="small text-muted text-uppercase mb-2">Danger zone</div>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                          onsubmit="return confirm('Permanently delete {{ $user->name }}\'s account? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Delete user
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endunless

<div class="card shadow-sm">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-hdd-stack"></i>
        </div>
        <div class="fw-semibold">Instances</div>
    </div>
    <div class="card-body">
        @if ($instances->isEmpty())
            <p class="text-muted mb-0">No instances yet.</p>
        @else
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Phone number</th>
                        <th>Sent</th>
                        <th>Failed</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instances as $instance)
                        @php
                            $statusColor = match ($instance->status) {
                                'connected' => 'success',
                                'qr_pending', 'connecting' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td>{{ $instance->name }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-{{ $statusColor }}">
                                    {{ str($instance->status)->replace('_', ' ') }}
                                </span>
                            </td>
                            <td>{{ $instance->phone_number ?? '—' }}</td>
                            <td>{{ $instance->messages_sent_count }}</td>
                            <td>{{ $instance->messages_failed_count }}</td>
                            <td>{{ $instance->messages_received_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if ($instances->count() > 1)
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="3">Total</td>
                            <td>{{ $instances->sum('messages_sent_count') }}</td>
                            <td>{{ $instances->sum('messages_failed_count') }}</td>
                            <td>{{ $instances->sum('messages_received_count') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            <p class="text-muted small mb-0 mt-3">
                <i class="bi bi-info-circle me-1"></i>Message counts only — phone numbers and message text are not shown here.
            </p>
        @endif
    </div>
</div>
@endsection
