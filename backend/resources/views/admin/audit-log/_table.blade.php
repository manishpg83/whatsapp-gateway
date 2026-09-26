{{-- Shared by the Audit log page and the admin user page. Expects $logs,
     optional $showTarget (default true) and optional $existingUserIds
     (users that still exist, so their name can link to their page).
     A normal table from lg up, stacked cards below (ad-rtable). --}}
@php
    $showTarget = $showTarget ?? true;
    $existingUserIds = $existingUserIds ?? collect();
    $actionStyles = [
        'user.plan_changed' => ['blue', 'bi-arrow-left-right'],
        'user.suspended' => ['amber', 'bi-slash-circle'],
        'user.unsuspended' => ['green', 'bi-check-circle'],
        'user.deleted' => ['red', 'bi-person-x'],
        'plan.created' => ['green', 'bi-plus-circle'],
        'plan.updated' => ['purple', 'bi-pencil'],
        'plan.deleted' => ['red', 'bi-trash'],
    ];
@endphp
<div class="ad-table-wrap">
    <table class="table ad-table ad-rtable ad-rtable-log mb-0 align-middle">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Admin</th>
                <th>Action</th>
                @if ($showTarget)
                    <th>Target</th>
                @endif
                <th>Details</th>
                <th>IP address</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                @php [$tone, $icon] = $actionStyles[$log->action] ?? ['grey', 'bi-circle']; @endphp
                <tr>
                    <td data-label="Date/Time" class="text-nowrap">
                        <span class="d-block small fw-semibold">{{ $log->created_at->format('Y-m-d') }}</span>
                        <span class="d-block small text-muted">{{ $log->created_at->format('H:i:s') }}</span>
                    </td>
                    <td data-label="Admin" class="text-nowrap">{{ $log->admin_name }}</td>
                    <td data-label="Action" class="text-nowrap">
                        <span class="ad-pill ad-tone-{{ $tone }}"><i class="bi {{ $icon }}"></i>{{ $log->actionLabel() }}</span>
                    </td>
                    @if ($showTarget)
                        <td data-label="Target" class="ad-cell-span2">
                            @if ($log->target_type === 'user' && $existingUserIds->contains($log->target_id))
                                <a href="{{ route('admin.users.show', $log->target_id) }}" class="fw-semibold text-decoration-none ad-break">{{ $log->target_label }}</a>
                            @else
                                <span class="fw-semibold ad-break">{{ $log->target_label }}</span>
                            @endif
                            <div class="text-muted small">{{ ucfirst($log->target_type) }}</div>
                        </td>
                    @endif
                    <td data-label="Details" class="ad-cell-details">
                        @forelse ($log->detailLines() as $line)
                            <span class="ad-detail">{{ $line }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td data-label="IP address" class="small text-muted ad-ip">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
