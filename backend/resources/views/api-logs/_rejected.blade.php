{{-- API Logs → "Rejected requests" tab. Expects $rejectedLogs. --}}
<div class="al-note db-in" style="--i: 2;">
    <i class="bi bi-info-circle"></i>
    <div>
        API calls that were refused before anything was sent —
        e.g. a wrong <code>instance_id</code>, the instance not connected, your plan limit reached, invalid
        input, too many requests, or a revoked token still being used. The <strong>Error</strong> column is
        exactly what the caller got back.
    </div>
</div>

@if ($rejectedLogs->isEmpty())
    <div class="card shadow-sm db-in" style="--i: 3;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi bi-check2-circle"></i></span>
            </div>
            <h2 class="h5 mb-0">No rejected requests — every call so far was accepted.</h2>
        </div>
    </div>
@else
    <div class="al-table-wrap db-in" style="--i: 3;">
        <table class="dc-table dc-stack al-rejected bg-white">
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
                        $tone = match (true) {
                            $log->status_code === 401 => 'bad',
                            $log->status_code >= 500 => 'err',
                            default => 'warn',
                        };
                    @endphp
                    <tr>
                        <td class="text-nowrap small">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                            <div class="al-endpoint">
                                <span class="dc-method dc-{{ strtolower($log->method) === 'get' ? 'get' : 'post' }}">{{ $log->method }}</span>
                                <span class="font-monospace">{{ $log->path }}</span>
                            </div>
                        </td>
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
                        <td><span class="dc-http dc-http-{{ $tone }}">{{ $log->status_code }}</span></td>
                        <td class="small al-error">{{ $log->error ?? '—' }}</td>
                        <td class="text-nowrap small">{{ $log->to_number ?? '—' }}</td>
                        <td class="text-nowrap small text-muted">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3 ms-pagination">
        {{ $rejectedLogs->links() }}
    </div>
@endif
