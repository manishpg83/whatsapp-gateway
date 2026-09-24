@extends('layouts.app')

@section('title', 'Admin · Instances')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Instances</h1>
    <p class="text-muted mb-0">Every WhatsApp instance on the platform — status only, never message content.</p>
</div>

@php
    $counterColors = [
        'connected' => 'success',
        'waiting' => 'info',
        'stuck' => 'warning',
        'disconnected' => 'secondary',
        'logged_out' => 'danger',
    ];
@endphp

{{-- Status counters — each one is a filter link --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.instances.index', array_filter(['search' => $search])) }}"
       class="btn btn-sm {{ $status === null ? 'btn-dark' : 'btn-outline-dark' }}">
        All <span class="badge text-bg-light ms-1">{{ array_sum($counts) }}</span>
    </a>
    @foreach ($filters as $value => $label)
        <a href="{{ route('admin.instances.index', array_filter(['status' => $value, 'search' => $search])) }}"
           class="btn btn-sm {{ $status === $value ? 'btn-'.$counterColors[$value] : 'btn-outline-'.$counterColors[$value] }}">
            {{ $label }} <span class="badge text-bg-light ms-1">{{ $counts[$value] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.instances.index') }}" class="row g-2 mb-3">
    @if ($status)
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="col-sm-8 col-md-6 col-lg-4">
        <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm"
               placeholder="Search instance name, owner name or email" aria-label="Search">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Search</button>
    </div>
    @if ($search !== '' || $status)
        <div class="col-auto">
            <a href="{{ route('admin.instances.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear</a>
        </div>
    @endif
</form>

@if ($instances->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            {{ $search !== '' || $status ? 'No instances match these filters.' : 'No instances yet.' }}
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Instance</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Phone number</th>
                        <th>Connected since</th>
                        <th>Last disconnect reason</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instances as $instance)
                        @php
                            [$badgeColor, $badgeLabel] = match (true) {
                                $instance->isStuck() => ['warning', 'Stuck'],
                                $instance->status === 'connected' => ['success', 'Connected'],
                                in_array($instance->status, \App\Models\WhatsappSession::WAITING_STATUSES, true) => ['info', 'Waiting for QR'],
                                $instance->status === 'logged_out' => ['danger', 'Logged out'],
                                default => ['secondary', ucfirst(str_replace('_', ' ', $instance->status))],
                            };
                        @endphp
                        <tr>
                            <td>{{ $instance->name }}</td>
                            <td>
                                <a href="{{ route('admin.users.show', $instance->user) }}" class="text-decoration-none">{{ $instance->user->name }}</a>
                                <div class="text-muted small">{{ $instance->user->email }}</div>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-{{ $badgeColor }}"
                                      @if ($badgeLabel === 'Stuck') title="No update from the worker for over {{ \App\Models\WhatsappSession::STUCK_AFTER_MINUTES }} minutes — is it running?" @endif>
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="text-nowrap">{{ $instance->phone_number ?? '—' }}</td>
                            <td class="text-nowrap small">
                                {{ $instance->status === 'connected' && $instance->connected_at ? $instance->connected_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 220px;" title="{{ $instance->last_disconnect_reason }}">
                                {{ $instance->last_disconnect_reason ?? '—' }}
                            </td>
                            <td class="text-nowrap small">{{ $instance->created_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $instances->links() }}
    </div>

    @if ($counts['stuck'] > 0)
        <p class="text-muted small mt-2 mb-0">
            <i class="bi bi-info-circle me-1"></i><strong>Stuck</strong> = waiting to connect, but no update from the WhatsApp worker
            for over {{ \App\Models\WhatsappSession::STUCK_AFTER_MINUTES }} minutes. Check that the worker is running.
        </p>
    @endif
@endif
@endsection
