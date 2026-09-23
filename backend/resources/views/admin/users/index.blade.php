@extends('layouts.app')

@section('title', 'Admin · Users')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Users</h1>
    <p class="text-muted mb-0">Every registered account — {{ $users->count() }} total.</p>
</div>

<div class="card shadow-sm">
    <table class="table table-hover mb-0 align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Plan</th>
                <th>Instances</th>
                <th>Joined</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-badge">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            {{ $user->name }}
                            @if ($user->is_admin)
                                <span class="badge text-bg-secondary">Admin</span>
                            @endif
                            @if ($user->is_suspended)
                                <span class="badge text-bg-danger">Suspended</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge rounded-pill text-bg-{{ $user->subscription->plan === 'free' ? 'secondary' : 'success' }}">
                            {{ $user->subscription->planDetails()['name'] }}
                        </span>
                    </td>
                    <td>{{ $user->whatsapp_sessions_count }}</td>
                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
