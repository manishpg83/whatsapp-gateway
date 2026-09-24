{{-- API Logs → "Rejected requests" tab. Expects $rejectedLogs. --}}
<p class="text-muted small">
    Calls to <code>POST /api/v1/messages/send</code> that were refused before a message was created —
    e.g. a wrong <code>instance_id</code>, the instance not connected, your plan limit reached, invalid
    input, too many requests, or a revoked token still being used. The <strong>Error</strong> column is
    exactly what the caller got back.
</p>

@if ($rejectedLogs->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="bg-wa-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-3" style="width: 64px; height: 64px;">
                <i class="bi bi-check2-circle"></i>
            </div>
            <p class="text-muted mb-0">No rejected requests — every call so far was accepted.</p>
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Instance</th>
                        <th>Token</th>
                        <th>HTTP</th>
                        <th>Error</th>
                        <th>To</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rejectedLogs as $log)
                        @php
                            $color = match (true) {
                                $log->status_code === 401 => 'danger',
                                $log->status_code === 429 => 'warning',
                                $log->status_code >= 500 => 'dark',
                                default => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td class="text-nowrap small">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="text-nowrap">{{ $log->whatsappSession->name }}</td>
                            <td class="text-nowrap small">
                                @if ($log->apiToken)
                                    <i class="bi bi-key me-1"></i>{{ $log->apiToken->name }}
                                    @if ($log->apiToken->revoked_at)
                                        <span class="badge text-bg-secondary">Revoked</span>
                                    @endif
                                @else
                                    <span class="text-muted">Deleted token</span>
                                @endif
                            </td>
                            <td><span class="badge rounded-pill text-bg-{{ $color }}">{{ $log->status_code }}</span></td>
                            <td class="small" style="max-width: 360px;">{{ $log->error ?? '—' }}</td>
                            <td class="text-nowrap small">{{ $log->to_number ?? '—' }}</td>
                            <td class="text-nowrap small text-muted">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $rejectedLogs->links() }}
    </div>
@endif
