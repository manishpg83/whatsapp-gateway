@extends('layouts.app')

@section('title', 'Admin · Audit log')

@section('content')
{{-- Header --}}
<div class="mb-4 db-in">
    <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin</span>
    <h1 class="h3 mt-2 mb-1">Audit log</h1>
    <p class="text-muted mb-0">Every admin action — who did what, to whom, and when. Entries can't be edited or deleted.</p>
</div>

{{-- Action filter (links, so they keep the current search) --}}
<div class="in-tabs mb-3 db-in" style="--i: 1;" role="tablist" aria-label="Filter by action">
    <a href="{{ route('admin.audit-log.index', array_filter(['search' => $search])) }}"
       class="in-tab text-decoration-none {{ $action === null ? 'active' : '' }}" role="tab" aria-selected="{{ $action === null ? 'true' : 'false' }}">All actions</a>
    @foreach ($actions as $value => $label)
        <a href="{{ route('admin.audit-log.index', array_filter(['action' => $value, 'search' => $search])) }}"
           class="in-tab text-decoration-none {{ $action === $value ? 'active' : '' }}" role="tab" aria-selected="{{ $action === $value ? 'true' : 'false' }}">{{ $label }}</a>
    @endforeach
</div>

{{-- Search --}}
<form method="GET" action="{{ route('admin.audit-log.index') }}" class="d-flex align-items-center gap-2 mb-4 db-in" style="--i: 2;">
    @if ($action)
        <input type="hidden" name="action" value="{{ $action }}">
    @endif
    <div class="in-search ad-search">
        <i class="bi bi-search"></i>
        <input type="search" id="filter-search" name="search" value="{{ $search }}" class="form-control"
               placeholder="Admin name, user name/email or plan" aria-label="Search">
    </div>
    <button type="submit" class="btn btn-primary ad-btn-lift">Search</button>
    @if ($search !== '' || $action)
        <a href="{{ route('admin.audit-log.index') }}" class="btn btn-light border text-nowrap"><i class="bi bi-x-lg me-1"></i>Clear</a>
    @endif
</form>

@if ($logs->isEmpty())
    <div class="ad-panel h-auto text-center text-muted py-5">
        <i class="bi bi-journal fs-1 d-block mb-2"></i>
        {{ $search !== '' || $action ? 'No entries match these filters.' : 'No admin actions recorded yet.' }}
    </div>
@else
    <div class="ad-panel h-auto db-in" style="--i: 3;">
        <div class="ad-panel-head">
            <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-journal-text"></i></span>
            <div>
                <div class="fw-semibold">{{ $action ? $actions[$action] : 'All actions' }}</div>
                <div class="text-muted small">{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}, newest first</div>
            </div>
            <span class="ms-auto ad-pill ad-tone-grey d-none d-sm-inline-flex"><i class="bi bi-lock"></i>Read-only</span>
        </div>
        @include('admin.audit-log._table', ['logs' => $logs, 'existingUserIds' => $existingUserIds])
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endif
@endsection
