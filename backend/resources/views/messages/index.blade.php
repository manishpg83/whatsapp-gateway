@extends('layouts.app')

@section('title', 'Messages')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-center gap-2">
        <span class="bg-wa-light text-primary rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
            <i class="bi bi-chat-left-text"></i>
        </span>
        <div>
            <h1 class="h4 mb-0">Messages</h1>
            <div class="text-muted small">Every message sent and received, across all your instances.</div>
        </div>
    </div>
</div>

@php
    $hasFilters = $filters['instance_id'] || $filters['direction'] || $filters['status'] || $filters['type'];
@endphp

<form method="GET" action="{{ route('messages.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-12 col-sm-6 col-lg-3">
        <label for="filter-instance" class="form-label small text-muted mb-1">Instance</label>
        <select id="filter-instance" name="instance_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All instances</option>
            @foreach ($instances as $instance)
                <option value="{{ $instance->instance_id }}" @selected($filters['instance_id'] === $instance->instance_id)>
                    {{ $instance->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-sm-3 col-lg-2">
        <label for="filter-direction" class="form-label small text-muted mb-1">Direction</label>
        <select id="filter-direction" name="direction" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="outgoing" @selected($filters['direction'] === 'outgoing')>Sent</option>
            <option value="incoming" @selected($filters['direction'] === 'incoming')>Received</option>
        </select>
    </div>
    <div class="col-6 col-sm-3 col-lg-2">
        <label for="filter-status" class="form-label small text-muted mb-1">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach (['sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'pending' => 'Pending', 'received' => 'Received'] as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <label for="filter-type" class="form-label small text-muted mb-1">Type</label>
        <select id="filter-type" name="type" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach (\App\Models\Message::TYPES as $value => [$label])
                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if ($hasFilters)
        <div class="col-auto">
            <a href="{{ route('messages.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear filters</a>
        </div>
    @endif
    <noscript><div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div></noscript>
</form>

@if ($messages->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="bg-wa-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-3" style="width: 64px; height: 64px;">
                <i class="bi bi-chat-left-text"></i>
            </div>
            @if ($hasFilters)
                <p class="text-muted mb-1">No messages match these filters.</p>
                <p class="text-muted small mb-0"><a href="{{ route('messages.index') }}">Clear filters</a> to see all messages.</p>
            @else
                <p class="text-muted mb-1">No messages yet.</p>
                <p class="text-muted small mb-0">
                    Connect an instance on the <a href="{{ route('instances.index') }}">Instances</a> page,
                    then send a test message or use the API.
                </p>
            @endif
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Direction</th>
                        <th>Instance</th>
                        <th>From / To</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date/Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messages as $message)
                        @php
                            $isIncoming = $message->direction === 'incoming';
                        @endphp
                        <tr>
                            <td class="text-nowrap">
                                @if ($isIncoming)
                                    <i class="bi bi-arrow-down-left text-primary me-1"></i>Received
                                @else
                                    <i class="bi bi-arrow-up-right text-success me-1"></i>Sent
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('instances.show', $message->whatsappSession) }}" class="text-decoration-none">{{ $message->whatsappSession->name }}</a>
                            </td>
                            <td class="text-nowrap">
                                <span class="text-muted small">{{ $isIncoming ? 'From' : 'To' }}</span>
                                {{ ($isIncoming ? $message->from_number : $message->to_number) ?? '—' }}
                            </td>
                            <td>@include('messages._content', ['message' => $message, 'compact' => true])</td>
                            <td><x-message-status :message="$message" /></td>
                            <td class="text-nowrap small">{{ $message->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="collapse" data-bs-target="#message-{{ $message->id }}"
                                        aria-expanded="false" aria-controls="message-{{ $message->id }}">
                                    View
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="message-{{ $message->id }}">
                            <td colspan="7" class="bg-light-subtle">
                                <div class="mb-2 small fw-semibold text-uppercase text-muted">Full message</div>
                                <div class="bg-light rounded p-3 small">@include('messages._content', ['message' => $message])</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $messages->links() }}
    </div>
@endif
@endsection
