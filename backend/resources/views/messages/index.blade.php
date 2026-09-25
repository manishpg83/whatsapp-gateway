@extends('layouts.app')

@section('title', 'Messages')

@section('content')
@php
    $hasFilters = $filters['instance_id'] || $filters['direction'] || $filters['status'] || $filters['type'];

    // URL for the current filters with some changed (null removes one).
    $filterUrl = fn (array $changes) => route('messages.index', array_filter(array_merge($filters, $changes)));

    $statusOptions = ['sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'pending' => 'Pending', 'received' => 'Received'];
    $instanceNames = $instances->pluck('name', 'instance_id');

    // Active-filter chips: [label, url that removes just this filter].
    $chips = array_filter([
        $filters['instance_id'] ? ['Instance: '.($instanceNames[$filters['instance_id']] ?? 'Unknown'), $filterUrl(['instance_id' => null])] : null,
        $filters['direction'] ? [$filters['direction'] === 'outgoing' ? 'Sent only' : 'Received only', $filterUrl(['direction' => null])] : null,
        $filters['status'] ? ['Status: '.($statusOptions[$filters['status']] ?? $filters['status']), $filterUrl(['status' => null])] : null,
        $filters['type'] ? ['Type: '.(\App\Models\Message::TYPES[$filters['type']][0] ?? $filters['type']), $filterUrl(['type' => null])] : null,
    ]);

    $tiles = [
        ['Total', $totals['total'], 'bi-chat-left-text', 'green', $filterUrl(['direction' => null, 'status' => null]), ! $filters['direction'] && ! $filters['status']],
        ['Sent', $totals['sent'], 'bi-arrow-up-right', 'blue', $filterUrl(['direction' => 'outgoing', 'status' => null]), $filters['direction'] === 'outgoing' && ! $filters['status']],
        ['Received', $totals['received'], 'bi-arrow-down-left', 'purple', $filterUrl(['direction' => 'incoming', 'status' => null]), $filters['direction'] === 'incoming' && ! $filters['status']],
        ['Failed', $totals['failed'], 'bi-exclamation-triangle', 'red', $filterUrl(['direction' => null, 'status' => 'failed']), $filters['status'] === 'failed'],
    ];
@endphp

{{-- Header --}}
<div class="d-flex align-items-center gap-3 mb-4 db-in">
    <span class="ms-head-icon"><i class="bi bi-chat-left-text"></i></span>
    <div>
        <h1 class="h3 mb-0">Messages</h1>
        <div class="text-muted small">Every message sent and received, across all your instances.</div>
    </div>
</div>

{{-- Summary tiles (click to filter) --}}
<div class="row g-3 mb-4">
    @foreach ($tiles as $i => [$label, $count, $icon, $tone, $url, $active])
        <div class="col-6 col-xl-3">
            <a href="{{ $url }}" class="ms-tile ms-tone-{{ $tone }} {{ $active ? 'active' : '' }} db-in" style="--i: {{ $i + 1 }};">
                <span class="ms-tile-icon"><i class="bi {{ $icon }}"></i></span>
                <span class="d-block" style="min-width: 0;">
                    <span class="ms-tile-label">{{ $label }}</span>
                    <span class="ms-tile-value" data-count-up="{{ $count }}">{{ $count }}</span>
                </span>
            </a>
        </div>
    @endforeach
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4 db-in" style="--i: 5;">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('messages.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-lg-auto">
                <span class="form-label small text-muted mb-1 d-block">Direction</span>
                <div class="ms-seg" role="group" aria-label="Direction">
                    @foreach (['' => 'All', 'outgoing' => 'Sent', 'incoming' => 'Received'] as $value => $label)
                        <a href="{{ $filterUrl(['direction' => $value ?: null]) }}"
                           class="ms-seg-btn {{ ($filters['direction'] ?? '') === $value ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                {{-- Keeps the direction when the dropdowns below submit the form. --}}
                @if ($filters['direction'])
                    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                @endif
            </div>
            <div class="col-12 col-sm-6 col-lg">
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
            <div class="col-6 col-sm-3 col-lg">
                <label for="filter-status" class="form-label small text-muted mb-1">Status</label>
                <select id="filter-status" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3 col-lg">
                <label for="filter-type" class="form-label small text-muted mb-1">Type</label>
                <select id="filter-type" name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach (\App\Models\Message::TYPES as $value => [$label])
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div></noscript>
        </form>

        @if ($chips)
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
                <span class="small text-muted me-1">Filtered by:</span>
                @foreach ($chips as [$label, $removeUrl])
                    <a href="{{ $removeUrl }}" class="ms-chip" title="Remove this filter">{{ $label }} <i class="bi bi-x"></i></a>
                @endforeach
                <a href="{{ route('messages.index') }}" class="small text-decoration-none ms-1">Clear filters</a>
            </div>
        @endif
    </div>
</div>

@if ($messages->isEmpty())
    <div class="card shadow-sm db-in" style="--i: 6;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi {{ $hasFilters ? 'bi-funnel' : 'bi-chat-dots' }}"></i></span>
            </div>
            @if ($hasFilters)
                <h2 class="h5 mb-2">No messages match these filters.</h2>
                <p class="text-muted mb-0"><a href="{{ route('messages.index') }}">Clear filters</a> to see all messages.</p>
            @else
                <h2 class="h5 mb-2">No messages yet.</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 30rem;">
                    Connect an instance on the <a href="{{ route('instances.index') }}">Instances</a> page,
                    then send a test message or use the API.
                </p>
            @endif
        </div>
    </div>
@else
    {{-- Message list, grouped by day --}}
    <div class="card shadow-sm overflow-hidden db-in" style="--i: 6;">
        @foreach ($messages->groupBy(fn ($m) => $m->created_at->toDateString()) as $day => $dayMessages)
            @php
                $dayDate = \Illuminate\Support\Carbon::parse($day);
                $dayLabel = match (true) {
                    $dayDate->isToday() => 'Today',
                    $dayDate->isYesterday() => 'Yesterday',
                    default => $dayDate->format('D, M j, Y'),
                };
            @endphp
            <div class="ms-day">
                <span><i class="bi bi-calendar3 me-1"></i>{{ $dayLabel }}</span>
                <span class="text-muted">{{ $dayMessages->count() }} {{ Str::plural('message', $dayMessages->count()) }}</span>
            </div>

            @foreach ($dayMessages as $message)
                @php $isIncoming = $message->direction === 'incoming'; @endphp
                <div class="ms-row {{ $isIncoming ? 'is-in' : 'is-out' }}" style="--i: {{ min($loop->parent->index * 3 + $loop->index, 12) }};">
                    <div class="ms-row-main" data-bs-toggle="collapse" data-bs-target="#message-{{ $message->id }}"
                         role="button" tabindex="0" aria-expanded="false" aria-controls="message-{{ $message->id }}">
                        <span class="direction-bubble {{ $isIncoming ? 'in' : 'out' }} flex-shrink-0" title="{{ $isIncoming ? 'Received' : 'Sent' }}">
                            <i class="bi {{ $isIncoming ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}"></i>
                        </span>

                        <div class="ms-row-who">
                            <div class="fw-semibold text-truncate">
                                <span class="text-muted small fw-normal">{{ $isIncoming ? 'From' : 'To' }}</span>
                                {{ ($isIncoming ? $message->from_number : $message->to_number) ?? '—' }}
                            </div>
                            <a href="{{ route('instances.show', $message->whatsappSession) }}" class="ms-instance text-truncate">
                                <i class="bi bi-hdd-stack"></i>{{ $message->whatsappSession->name }}
                            </a>
                        </div>

                        <div class="ms-row-content">@include('messages._content', ['message' => $message, 'compact' => true])</div>

                        <div class="ms-row-meta">
                            <x-message-status :message="$message" />
                            <span class="ms-time" title="{{ $message->created_at->format('Y-m-d H:i:s') }}">{{ $message->created_at->format('H:i') }}</span>
                            <i class="bi bi-chevron-down ms-chevron" aria-hidden="true"></i>
                        </div>
                    </div>

                    <div class="collapse" id="message-{{ $message->id }}">
                        <div class="ms-detail">
                            <div class="ms-detail-bubble {{ $isIncoming ? 'in' : 'out' }}">
                                @include('messages._content', ['message' => $message])
                            </div>
                            <dl class="ms-detail-meta">
                                <div><dt>{{ $isIncoming ? 'Received' : 'Sent' }}</dt><dd>{{ $message->created_at->format('Y-m-d H:i:s') }}</dd></div>
                                @if ($message->delivered_at)
                                    <div><dt>Delivered</dt><dd>{{ $message->delivered_at->format('Y-m-d H:i:s') }}</dd></div>
                                @endif
                                @if ($message->read_at)
                                    <div><dt>Read</dt><dd>{{ $message->read_at->format('Y-m-d H:i:s') }}</dd></div>
                                @endif
                                @if ($message->whatsapp_message_id)
                                    <div><dt>Message ID</dt><dd class="font-monospace text-break">{{ $message->whatsapp_message_id }}</dd></div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    <div class="mt-3 ms-pagination">
        {{ $messages->links() }}
    </div>
@endif

<script>
document.querySelectorAll('.ms-row-main[role="button"]').forEach((row) => {
    // Rows open with Enter/Space too (they act as buttons for the collapse).
    row.addEventListener('keydown', (event) => {
        if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            row.click();
        }
    });

    // Links inside a row (instance name, open/download media) just follow
    // the link — they don't also expand the row.
    row.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', (event) => event.stopPropagation());
    });
});
</script>
@endsection
