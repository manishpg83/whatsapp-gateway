@extends('layouts.app')

@section('title', 'Admin · ' . $user->name)

@section('content')
@php
    $avatarTones = ['green', 'blue', 'purple', 'amber'];
    $subStatus = $user->subscription->status;
    $subTone = match ($subStatus) {
        'active' => 'green',
        'pending' => 'amber',
        'past_due' => 'red',
        default => 'grey',
    };
    $isPaid = $user->subscription->plan !== 'free';
    $connected = $instances->where('status', 'connected')->count();
    $connectedPct = $instances->count() > 0 ? round($connected / $instances->count() * 100) : 0;
    $sent = $instances->sum('messages_sent_count');
    $received = $instances->sum('messages_received_count');
    $failed = $instances->sum('messages_failed_count');
    $messageTotal = $sent + $received + $failed;
@endphp

<a href="{{ route('admin.users.index') }}" class="ad-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Users
</a>

{{-- Profile header --}}
<div class="db-hero db-in ad-hero rounded-4 p-4 mb-4 d-flex flex-column flex-md-row align-items-md-center gap-3 gap-md-4" style="--i: 1;">
    <span class="db-orb db-orb-1" aria-hidden="true"></span>

    <span class="ad-avatar ad-avatar-lg ad-tone-{{ $avatarTones[$user->id % count($avatarTones)] }}">
        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
    </span>

    <div style="min-width: 0;">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h1 class="h3 mb-0 text-break">{{ $user->name }}</h1>
            @if ($user->is_admin)
                <span class="ad-pill ad-tone-grey"><i class="bi bi-shield-lock"></i>Admin</span>
            @endif
            @if ($user->is_suspended)
                <span class="ad-pill ad-tone-red"><i class="bi bi-slash-circle"></i>Suspended</span>
            @elseif (! $user->email_verified_at)
                <span class="ad-pill ad-tone-amber"><i class="bi bi-envelope"></i>Unverified</span>
            @else
                <span class="ad-pill ad-tone-green"><i class="bi bi-check-circle"></i>Active</span>
            @endif
        </div>
        <div class="text-muted text-break mt-1"><i class="bi bi-envelope me-1"></i>{{ $user->email }}</div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <span class="ad-chip"><i class="bi bi-hash"></i>User {{ $user->id }}</span>
            <span class="ad-chip"><i class="bi bi-calendar3"></i>Joined {{ $user->created_at->format('M j, Y') }}</span>
            @if ($user->email_verified_at)
                <span class="ad-chip"><i class="bi bi-patch-check"></i>Verified {{ $user->email_verified_at->format('M j, Y') }}</span>
            @endif
        </div>
    </div>
</div>

{{-- Stat tiles --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 2;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Plan</span>
                <span class="ad-stat-icon ad-tone-purple"><i class="bi {{ $isPaid ? 'bi-gem' : 'bi-layers' }}"></i></span>
            </div>
            <div class="ad-stat-value ad-stat-value-sm">{{ $user->subscription->planDetails()['name'] }}</div>
            <div class="ad-stat-note">
                <span class="ad-pill ad-tone-{{ $subTone }}">Status: {{ ucfirst(str_replace('_', ' ', $subStatus)) }}</span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 3;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Instances</span>
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-hdd-stack"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $instances->count() }}">{{ $instances->count() }}</div>
            <div class="ad-stat-note">
                <span class="db-live {{ $connected > 0 ? 'is-on' : '' }}" aria-hidden="true"></span>{{ $connected }} connected
            </div>
            <div class="db-progress" role="progressbar" aria-label="Connected instances"
                 aria-valuenow="{{ $connectedPct }}" aria-valuemin="0" aria-valuemax="100">
                <span style="--pct: {{ $connectedPct }}%;"></span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 4;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Messages this month</span>
                <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-chat-left-text"></i></span>
            </div>
            <div class="ad-stack" aria-hidden="true">
                @if ($messageTotal > 0)
                    <span class="ad-stack-sent" style="width: {{ $sent / $messageTotal * 100 }}%;"></span>
                    <span class="ad-stack-received" style="width: {{ $received / $messageTotal * 100 }}%;"></span>
                    <span class="ad-stack-failed" style="width: {{ $failed / $messageTotal * 100 }}%;"></span>
                @endif
            </div>
            <ul class="ad-legend ad-legend-stacked">
                <li><span class="ad-dot ad-stack-sent"></span>{{ $sent }} sent</li>
                <li><span class="ad-dot ad-stack-received"></span>{{ $received }} received</li>
                <li class="{{ $failed > 0 ? 'text-danger fw-semibold' : '' }}"><span class="ad-dot ad-stack-failed"></span>{{ $failed }} failed</li>
            </ul>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 5;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Joined</span>
                <span class="ad-stat-icon ad-tone-amber"><i class="bi bi-calendar-check"></i></span>
            </div>
            <div class="ad-stat-value ad-stat-value-sm">{{ $user->created_at->format('M j, Y') }}</div>
            <div class="ad-stat-note">{{ $user->created_at->diffForHumans() }}</div>
        </div>
    </div>
</div>

{{-- Manage account --}}
@unless ($user->is_admin || $user->id === auth()->id())
    <div class="ad-panel h-auto mb-4 db-in" style="--i: 6;">
        <div class="ad-panel-head">
            <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-gear"></i></span>
            <div>
                <div class="fw-semibold">Manage account</div>
                <div class="text-muted small">Every change here is recorded in the audit log</div>
            </div>
        </div>
        <div class="row g-3 p-3 p-md-4">
            <div class="col-lg-5">
                <div class="ad-action">
                    <div class="ad-action-title"><i class="bi bi-layers"></i> Plan</div>
                    <form method="POST" action="{{ route('admin.users.plan.update', $user) }}" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <select name="plan" class="form-select" aria-label="Plan">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->slug }}" @selected($user->subscription->plan === $plan->slug)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary text-nowrap">Change</button>
                    </form>
                    <div class="form-text mt-2">Local override only — does not touch any real Cashfree subscription.</div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="ad-action">
                    <div class="ad-action-title"><i class="bi bi-door-open"></i> Login access</div>
                    @if ($user->is_suspended)
                        <p class="text-muted small mb-3">This account is suspended and cannot log in.</p>
                        <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-success">
                                <i class="bi bi-check-circle me-1"></i>Unsuspend
                            </button>
                        </form>
                    @else
                        <p class="text-muted small mb-3">Logs them out and blocks login until unsuspended.</p>
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}"
                              onsubmit="return confirm('Suspend {{ $user->name }}? They will be logged out and unable to log back in until unsuspended.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning">
                                <i class="bi bi-slash-circle me-1"></i>Suspend
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="ad-action ad-action-danger">
                    <div class="ad-action-title"><i class="bi bi-exclamation-octagon"></i> Danger zone</div>
                    <p class="small mb-3">Permanently deletes the account.</p>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                          onsubmit="return confirm('Permanently delete {{ $user->name }}\'s account? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endunless

{{-- Instances --}}
<div class="ad-panel h-auto mb-4 db-in" style="--i: 7;">
    <div class="ad-panel-head">
        <span class="ad-stat-icon ad-tone-green"><i class="bi bi-hdd-stack"></i></span>
        <div>
            <div class="fw-semibold">Instances</div>
            <div class="text-muted small">Message counts are for this calendar month</div>
        </div>
    </div>
    @if ($instances->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="bi bi-hdd-stack fs-2 d-block mb-2"></i>
            No instances yet.
        </div>
    @else
        <div class="ad-table-wrap">
            <table class="table ad-table ad-rtable ad-rtable-counts mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Phone number</th>
                        <th class="text-center">Sent</th>
                        <th class="text-center">Failed</th>
                        <th class="text-center">Received</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instances as $instance)
                        @php
                            $statusTone = match ($instance->status) {
                                'connected' => 'green',
                                'qr_pending', 'connecting' => 'amber',
                                default => 'grey',
                            };
                        @endphp
                        <tr>
                            <td class="ad-cell-user">
                                <span class="d-flex align-items-center gap-2">
                                    <span class="ad-avatar ad-avatar-sm ad-tone-green"><i class="bi bi-whatsapp"></i></span>
                                    <span class="fw-semibold text-break">{{ $instance->name }}</span>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="ad-pill ad-tone-{{ $statusTone }} text-capitalize">
                                    <span class="db-live {{ $instance->status === 'connected' ? 'is-on' : '' }} m-0" aria-hidden="true"></span>
                                    {{ str($instance->status)->replace('_', ' ') }}
                                </span>
                            </td>
                            <td data-label="Phone number">{{ $instance->phone_number ?? '—' }}</td>
                            <td data-label="Sent" class="text-lg-center">{{ $instance->messages_sent_count }}</td>
                            <td data-label="Failed" class="text-lg-center {{ $instance->messages_failed_count > 0 ? 'text-danger fw-semibold' : '' }}">{{ $instance->messages_failed_count }}</td>
                            <td data-label="Received" class="text-lg-center">{{ $instance->messages_received_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if ($instances->count() > 1)
                    <tfoot>
                        <tr>
                            <td colspan="3" class="ad-cell-user">Total</td>
                            <td data-label="Sent" class="text-lg-center">{{ $instances->sum('messages_sent_count') }}</td>
                            <td data-label="Failed" class="text-lg-center">{{ $instances->sum('messages_failed_count') }}</td>
                            <td data-label="Received" class="text-lg-center">{{ $instances->sum('messages_received_count') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="ad-panel-foot">
            <i class="bi bi-info-circle me-1"></i>Message counts only — phone numbers and message text are not shown here.
        </div>
    @endif
</div>

{{-- Audit trail for this account --}}
<div class="ad-panel h-auto db-in" style="--i: 8;">
    <div class="ad-panel-head">
        <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-journal-text"></i></span>
        <div class="fw-semibold">Admin actions on this account</div>
        <a href="{{ route('admin.audit-log.index', ['search' => $user->email]) }}" class="ms-auto small fw-semibold text-decoration-none text-nowrap">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    @if ($auditLogs->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="bi bi-journal fs-2 d-block mb-2"></i>
            No admin actions on this account yet.
        </div>
    @else
        @include('admin.audit-log._table', ['logs' => $auditLogs, 'showTarget' => false])
    @endif
</div>
@endsection
