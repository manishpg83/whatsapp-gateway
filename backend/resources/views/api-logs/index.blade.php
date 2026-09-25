@extends('layouts.app')

@section('title', 'API Logs')

@section('content')
{{-- Header --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 db-in">
    <div class="d-flex align-items-center gap-3">
        <span class="ms-head-icon"><i class="bi bi-clock-history"></i></span>
        <div>
            <h1 class="h3 mb-0">API Logs</h1>
            <div class="text-muted small">Every real call to <code>POST /api/v1/messages/send</code>, across all your instances.</div>
        </div>
    </div>

    @if ($instances->count() > 1)
        <form method="GET" action="{{ route('api-logs.index') }}" class="al-filter">
            @if ($tab === 'rejected')
                <input type="hidden" name="tab" value="rejected">
            @endif
            <i class="bi bi-hdd-stack"></i>
            <label for="al-instance" class="visually-hidden">Instance</label>
            <select id="al-instance" name="instance_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All instances</option>
                @foreach ($instances as $instance)
                    <option value="{{ $instance->instance_id }}" @selected($selectedInstanceId === $instance->instance_id)>
                        {{ $instance->name }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif
</div>

{{-- Tabs --}}
<nav class="al-tabs mb-4 db-in" style="--i: 1;" aria-label="Log type">
    <a class="al-tab {{ $tab === 'calls' ? 'active' : '' }}"
       href="{{ route('api-logs.index', array_filter(['instance_id' => $selectedInstanceId])) }}">
        <i class="bi bi-send"></i>API calls
        <span class="al-tab-count">{{ $callsCount }}</span>
    </a>
    <a class="al-tab {{ $tab === 'rejected' ? 'active' : '' }}"
       href="{{ route('api-logs.index', array_filter(['tab' => 'rejected', 'instance_id' => $selectedInstanceId])) }}">
        <i class="bi bi-slash-circle"></i>Rejected requests
        @if ($rejectedCount > 0)
            <span class="al-tab-count al-tab-count-danger">{{ $rejectedCount }}</span>
        @endif
    </a>
</nav>

@if ($tab === 'rejected')
    @include('api-logs._rejected')
@elseif ($logs->isEmpty())
    <div class="card shadow-sm db-in" style="--i: 2;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi bi-braces"></i></span>
            </div>
            <h2 class="h5 mb-2">No API calls logged yet.</h2>
            <p class="text-muted mb-0 mx-auto" style="max-width: 30rem;">
                Generate a token on an instance's page, then see the
                <a href="{{ route('docs.index') }}">API Docs</a> for a ready-to-run example.
            </p>
        </div>
    </div>
@else
    <div class="card shadow-sm overflow-hidden db-in" style="--i: 2;">
        @foreach ($logs->groupBy(fn ($log) => $log->created_at->toDateString()) as $day => $dayLogs)
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
                <span class="text-muted">{{ $dayLogs->count() }} {{ Str::plural('call', $dayLogs->count()) }}</span>
            </div>

            @foreach ($dayLogs as $log)
                @php
                    $response = $log->apiResponseExample();
                    $httpTone = match (true) {
                        $response['status'] === 200 => 'ok',
                        $response['status'] >= 500 => 'err',
                        default => 'warn',
                    };
                @endphp
                <div class="al-row" style="--i: {{ min($loop->parent->index * 3 + $loop->index, 12) }};">
                    <div class="al-row-main" data-bs-toggle="collapse" data-bs-target="#log-{{ $log->id }}"
                         role="button" tabindex="0" aria-expanded="false" aria-controls="log-{{ $log->id }}">
                        <div class="al-row-req">
                            <span class="dc-method dc-post">POST</span>
                            <span class="dc-http dc-http-{{ $httpTone }}">{{ $response['status'] ?: '…' }}</span>
                        </div>

                        <div class="al-row-who">
                            <div class="fw-semibold text-truncate"><span class="text-muted small fw-normal">To</span> {{ $log->to_number }}</div>
                            <div class="al-sub text-truncate">
                                <i class="bi bi-hdd-stack"></i>{{ $log->whatsappSession->name }}
                                <span class="d-none d-sm-inline">&middot; from {{ $log->whatsappSession->phone_number ?? '—' }}</span>
                            </div>
                        </div>

                        <div class="al-row-body text-truncate">{{ $log->body }}</div>

                        <div class="al-row-token text-truncate">
                            <i class="bi bi-key"></i>{{ $log->apiToken->name ?? 'Deleted token' }}
                        </div>

                        <div class="al-row-meta">
                            <x-message-status :message="$log" />
                            <span class="ms-time" title="{{ $log->created_at->format('Y-m-d H:i:s') }}">{{ $log->created_at->format('H:i:s') }}</span>
                            <i class="bi bi-chevron-down ms-chevron" aria-hidden="true"></i>
                        </div>
                    </div>

                    <div class="collapse" id="log-{{ $log->id }}">
                        <div class="al-detail">
                            <dl class="al-detail-meta">
                                <div><dt>From number</dt><dd>{{ $log->whatsappSession->phone_number ?? '—' }}</dd></div>
                                <div><dt>Instance ID</dt><dd class="font-monospace text-break">{{ $log->whatsappSession->instance_id }}</dd></div>
                                <div><dt>Token</dt><dd>{{ $log->apiToken->name ?? 'Deleted token' }}</dd></div>
                                <div><dt>Date/Time</dt><dd>{{ $log->created_at->format('Y-m-d H:i:s') }}</dd></div>
                            </dl>

                            <div class="row g-3">
                                <div class="col-xl-7">
                                    <div class="dc-code">
                                        <div class="dc-code-head"><span>Request</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>{{ $log->apiCurlExample() }}</code></pre>
                                    </div>
                                </div>
                                <div class="col-xl-5">
                                    <div class="dc-code">
                                        <div class="dc-code-head"><span>Response</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>HTTP/1.1 {{ $response['status'] }}
{{ json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    <div class="mt-3 ms-pagination">
        {{ $logs->links() }}
    </div>
@endif

<script>
document.querySelectorAll('.al-row-main[role="button"]').forEach((row) => {
    // Rows open with Enter/Space too (they act as buttons for the collapse).
    row.addEventListener('keydown', (event) => {
        if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            row.click();
        }
    });
});
</script>
@endsection
