@extends('layouts.app')

@section('title', 'Admin · Audit log')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Audit log</h1>
    <p class="text-muted mb-0">Every admin action — who did what, to whom, and when. Entries can't be edited or deleted.</p>
</div>

<form method="GET" action="{{ route('admin.audit-log.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-sm-6 col-md-4 col-lg-3">
        <label for="filter-action" class="form-label small text-muted mb-1">Action</label>
        <select id="filter-action" name="action" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All actions</option>
            @foreach ($actions as $value => $label)
                <option value="{{ $value }}" @selected($action === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6 col-md-5 col-lg-4">
        <label for="filter-search" class="form-label small text-muted mb-1">Search</label>
        <input type="search" id="filter-search" name="search" value="{{ $search }}" class="form-control form-control-sm"
               placeholder="Admin name, user name/email or plan">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Search</button>
    </div>
    @if ($search !== '' || $action)
        <div class="col-auto">
            <a href="{{ route('admin.audit-log.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear</a>
        </div>
    @endif
</form>

@if ($logs->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            {{ $search !== '' || $action ? 'No entries match these filters.' : 'No admin actions recorded yet.' }}
        </div>
    </div>
@else
    <div class="card shadow-sm">
        @include('admin.audit-log._table', ['logs' => $logs, 'existingUserIds' => $existingUserIds])
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endif
@endsection
