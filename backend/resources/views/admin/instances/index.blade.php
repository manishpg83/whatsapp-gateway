@extends('layouts.app')

@section('title', 'Admin · Instances')

@section('content')
@php
    $total = array_sum($counts);
    $tones = [
        'connected' => 'green',
        'waiting' => 'blue',
        'stuck' => 'amber',
        'disconnected' => 'grey',
        'logged_out' => 'red',
    ];
    $icons = [
        'connected' => 'bi-wifi',
        'waiting' => 'bi-qr-code',
        'stuck' => 'bi-hourglass-split',
        'disconnected' => 'bi-wifi-off',
        'logged_out' => 'bi-box-arrow-right',
    ];
    $avatarTones = ['green', 'blue', 'purple', 'amber'];
@endphp

{{-- Header --}}
<div class="mb-4 db-in">
    <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin</span>
    <h1 class="h3 mt-2 mb-1">Instances</h1>
    <p class="text-muted mb-0">Every WhatsApp instance on the platform — status only, never message content.</p>
</div>

{{-- Stuck warning --}}
@if ($counts['stuck'] > 0)
    <div class="ad-alert ad-tone-amber mb-4 db-in" style="--i: 1;">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>{{ $counts['stuck'] }} {{ Str::plural('instance', $counts['stuck']) }} stuck.</strong>
            <strong>Stuck</strong> = waiting to connect, but no update from the WhatsApp worker
            for over {{ \App\Models\WhatsappSession::STUCK_AFTER_MINUTES }} minutes. Check that the worker is running.
        </div>
    </div>
@endif

{{-- Status tiles — each one is a filter link --}}
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-3">
    <div class="col">
        <a href="{{ route('admin.instances.index', array_filter(['search' => $search])) }}"
           class="ad-filter db-in {{ $status === null ? 'active' : '' }}" style="--i: 2;"
           @if ($status === null) aria-current="page" @endif>
            <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-hdd-stack"></i></span>
            <span>
                <span class="ad-stat-label d-block">All</span>
                <span class="ad-mini-value d-block">{{ $total }}</span>
            </span>
        </a>
    </div>
    @foreach ($filters as $value => $label)
        <div class="col">
            <a href="{{ route('admin.instances.index', array_filter(['status' => $value, 'search' => $search])) }}"
               class="ad-filter ad-filter-{{ $tones[$value] }} db-in {{ $status === $value ? 'active' : '' }}" style="--i: {{ $loop->iteration + 2 }};"
               @if ($status === $value) aria-current="page" @endif>
                <span class="ad-stat-icon ad-tone-{{ $tones[$value] }}"><i class="bi {{ $icons[$value] }}"></i></span>
                <span>
                    <span class="ad-stat-label d-block">{{ $label }}</span>
                    <span class="ad-mini-value d-block">{{ $counts[$value] }}</span>
                </span>
            </a>
        </div>
    @endforeach
</div>

{{-- Health bar: share of each status across the platform --}}
@if ($total > 0)
    <div class="ad-health mb-4 db-in" style="--i: 8;" role="img"
         aria-label="{{ collect($filters)->map(fn ($label, $key) => $counts[$key].' '.$label)->implode(', ') }}">
        @foreach ($filters as $value => $label)
            @if ($counts[$value] > 0)
                <span class="ad-dot-{{ $tones[$value] }}" style="width: {{ $counts[$value] / $total * 100 }}%;" title="{{ $label }}: {{ $counts[$value] }}"></span>
            @endif
        @endforeach
    </div>
@endif

{{-- Search --}}
<form method="GET" action="{{ route('admin.instances.index') }}" class="d-flex align-items-center gap-2 mb-3 db-in" style="--i: 9;">
    @if ($status)
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="in-search ad-search">
        <i class="bi bi-search"></i>
        <input type="search" name="search" value="{{ $search }}" class="form-control"
               placeholder="Search instance name, owner name or email" aria-label="Search">
    </div>
    <button type="submit" class="btn btn-primary ad-btn-lift">Search</button>
    @if ($search !== '' || $status)
        <a href="{{ route('admin.instances.index') }}" class="btn btn-light border text-nowrap"><i class="bi bi-x-lg me-1"></i>Clear</a>
    @endif
</form>

@if ($instances->isEmpty())
    <div class="ad-panel h-auto text-center text-muted py-5">
        <i class="bi bi-hdd-stack fs-1 d-block mb-2"></i>
        {{ $search !== '' || $status ? 'No instances match these filters.' : 'No instances yet.' }}
    </div>
@else
    <div class="ad-panel h-auto db-in" style="--i: 10;">
        <div class="ad-table-wrap">
            <table class="table ad-table ad-rtable mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Instance</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Connected since</th>
                        <th>Last disconnect reason</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instances as $instance)
                        @php
                            [$tone, $badgeLabel] = match (true) {
                                $instance->isStuck() => ['amber', 'Stuck'],
                                $instance->status === 'connected' => ['green', 'Connected'],
                                in_array($instance->status, \App\Models\WhatsappSession::WAITING_STATUSES, true) => ['blue', 'Waiting for QR'],
                                $instance->status === 'logged_out' => ['red', 'Logged out'],
                                default => ['grey', ucfirst(str_replace('_', ' ', $instance->status))],
                            };
                        @endphp
                        <tr>
                            <td class="ad-cell-user">
                                <span class="d-flex align-items-center gap-2">
                                    <span class="ad-avatar ad-avatar-sm ad-tone-green"><i class="bi bi-whatsapp"></i></span>
                                    <span>
                                        <span class="fw-semibold d-block ad-nowrap-lg">{{ $instance->name }}</span>
                                        <span class="text-muted small d-block text-nowrap"><i class="bi bi-telephone me-1"></i>{{ $instance->phone_number ?? '—' }}</span>
                                    </span>
                                </span>
                            </td>
                            <td data-label="Owner" class="ad-cell-span2">
                                <span class="d-flex align-items-center gap-2" style="min-width: 0;">
                                    <span class="ad-avatar ad-avatar-sm ad-tone-{{ $avatarTones[$instance->user->id % count($avatarTones)] }}">{{ mb_strtoupper(mb_substr($instance->user->name, 0, 1)) }}</span>
                                    <span style="min-width: 0;">
                                        <a href="{{ route('admin.users.show', $instance->user) }}" class="fw-semibold text-decoration-none d-block text-truncate ad-owner">{{ $instance->user->name }}</a>
                                        <span class="text-muted small d-block text-truncate ad-owner" title="{{ $instance->user->email }}">{{ $instance->user->email }}</span>
                                    </span>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="ad-pill ad-tone-{{ $tone }}"
                                      @if ($badgeLabel === 'Stuck') title="No update from the worker for over {{ \App\Models\WhatsappSession::STUCK_AFTER_MINUTES }} minutes — is it running?" @endif>
                                    <span class="db-live {{ $instance->status === 'connected' ? 'is-on' : '' }} m-0" aria-hidden="true"></span>
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td data-label="Connected since" class="text-nowrap small">
                                @if ($instance->status === 'connected' && $instance->connected_at)
                                    <span class="d-block">{{ $instance->connected_at->format('Y-m-d') }}</span>
                                    <span class="d-block text-muted">{{ $instance->connected_at->format('H:i') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Last disconnect reason" class="ad-cell-wide small text-muted">
                                <span class="ad-reason" title="{{ $instance->last_disconnect_reason }}">{{ $instance->last_disconnect_reason ?? '—' }}</span>
                            </td>
                            <td class="d-none d-lg-table-cell text-nowrap small">{{ $instance->created_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $instances->links() }}
    </div>
@endif
@endsection
