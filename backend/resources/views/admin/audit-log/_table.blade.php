{{-- Shared by the Audit log page and the admin user page. Expects $logs,
     optional $showTarget (default true) and optional $existingUserIds
     (users that still exist, so their name can link to their page). --}}
@php
    $showTarget = $showTarget ?? true;
    $existingUserIds = $existingUserIds ?? collect();
    $actionColors = [
        'user.suspended' => 'warning',
        'user.deleted' => 'danger',
        'plan.deleted' => 'danger',
        'user.unsuspended' => 'success',
        'plan.created' => 'success',
    ];
@endphp
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
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
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="text-nowrap">{{ $log->admin_name }}</td>
                    <td class="text-nowrap">
                        <span class="badge rounded-pill text-bg-{{ $actionColors[$log->action] ?? 'secondary' }}">{{ $log->actionLabel() }}</span>
                    </td>
                    @if ($showTarget)
                        <td>
                            @if ($log->target_type === 'user' && $existingUserIds->contains($log->target_id))
                                <a href="{{ route('admin.users.show', $log->target_id) }}" class="text-decoration-none">{{ $log->target_label }}</a>
                            @else
                                {{ $log->target_label }}
                            @endif
                            <div class="text-muted small">{{ ucfirst($log->target_type) }}</div>
                        </td>
                    @endif
                    <td class="small">
                        @forelse ($log->detailLines() as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td class="small text-muted text-nowrap">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
