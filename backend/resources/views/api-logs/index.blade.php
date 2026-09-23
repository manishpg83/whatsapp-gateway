@extends('layouts.app')

@section('title', 'API Logs')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-center gap-2">
        <span class="bg-wa-light text-primary rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
            <i class="bi bi-clock-history"></i>
        </span>
        <div>
            <h1 class="h4 mb-0">API Logs</h1>
            <div class="text-muted small">Every real call to <code>POST /api/v1/messages/send</code>, across all your instances.</div>
        </div>
    </div>

    @if ($instances->count() > 1)
        <form method="GET" action="{{ route('api-logs.index') }}">
            <select name="instance_id" class="form-select form-select-sm" onchange="this.form.submit()">
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

@if ($logs->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="bg-wa-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-3" style="width: 64px; height: 64px;">
                <i class="bi bi-clock-history"></i>
            </div>
            <p class="text-muted mb-1">No API calls logged yet.</p>
            <p class="text-muted small mb-0">
                Generate a token on an instance's page, then see the
                <a href="{{ route('docs.index') }}">API Docs</a> for a ready-to-run example.
            </p>
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>From Number</th>
                        <th>Instance ID</th>
                        <th>Token</th>
                        <th>To Number</th>
                        <th>Date/Time</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        @php
                            $response = $log->apiResponseExample();
                            $statusColor = match (true) {
                                $log->status === 'sent' => 'success',
                                $log->status === 'failed' => 'danger',
                                default => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $log->whatsappSession->phone_number ?? '—' }}</td>
                            <td><code class="small">{{ $log->whatsappSession->instance_id }}</code></td>
                            <td class="text-nowrap"><i class="bi bi-key me-1"></i>{{ $log->apiToken->name ?? 'Deleted token' }}</td>
                            <td class="text-nowrap">{{ $log->to_number }}</td>
                            <td class="text-nowrap small">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="text-truncate" style="max-width: 260px;">{{ $log->body }}</td>
                            <td><span class="badge rounded-pill text-bg-{{ $statusColor }}">{{ ucfirst($log->status) }}</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="collapse" data-bs-target="#log-{{ $log->id }}"
                                        aria-expanded="false" aria-controls="log-{{ $log->id }}">
                                    View
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="log-{{ $log->id }}">
                            <td colspan="8" class="bg-light-subtle">
                                <div class="mb-2 small fw-semibold text-uppercase text-muted">Request</div>
                                <pre class="bg-light rounded p-3 mb-3 small"><code>{{ $log->apiCurlExample() }}</code></pre>

                                <div class="mb-2 small fw-semibold text-uppercase text-muted">Response</div>
                                <pre class="bg-light rounded p-3 mb-0 small"><code>HTTP/1.1 {{ $response['status'] }}
{{ json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endif
@endsection
