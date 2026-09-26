@extends('layouts.app')

@section('title', 'Admin · Users')

@section('content')
@php
    // Counts for the summary strip + filter tabs, from the list already loaded.
    $counts = [
        'all' => $users->count(),
        'paid' => $users->filter(fn ($u) => $u->subscription->plan !== 'free')->count(),
        'free' => $users->filter(fn ($u) => $u->subscription->plan === 'free')->count(),
        'suspended' => $users->where('is_suspended', true)->count(),
        'unverified' => $users->whereNull('email_verified_at')->count(),
    ];
    $tabs = ['all' => 'All', 'paid' => 'Paid', 'free' => 'Free', 'suspended' => 'Suspended', 'unverified' => 'Unverified'];
    $avatarTones = ['green', 'blue', 'purple', 'amber'];
@endphp

{{-- Header --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4 db-in">
    <div>
        <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin</span>
        <h1 class="h3 mt-2 mb-1">Users</h1>
        <p class="text-muted mb-0">Every registered account — {{ $users->count() }} total.</p>
    </div>
</div>

{{-- Summary strip --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Total users', $counts['all'], 'bi-people', 'green'],
        ['Paid plan', $counts['paid'], 'bi-gem', 'blue'],
        ['Suspended', $counts['suspended'], 'bi-slash-circle', $counts['suspended'] > 0 ? 'red' : 'grey'],
        ['Unverified', $counts['unverified'], 'bi-envelope-exclamation', $counts['unverified'] > 0 ? 'amber' : 'grey'],
    ] as [$label, $value, $icon, $tone])
        <div class="col-6 col-xl-3">
            <div class="ad-mini db-in" style="--i: {{ $loop->iteration }};">
                <span class="ad-stat-icon ad-tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                <div>
                    <div class="ad-stat-label">{{ $label }}</div>
                    <div class="ad-mini-value" data-count-up="{{ $value }}">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if ($users->isEmpty())
    <div class="ad-panel h-auto text-center text-muted py-5">
        <i class="bi bi-people fs-1 d-block mb-2"></i>
        No users yet.
    </div>
@else
    {{-- Filter tabs + search --}}
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3 db-in" style="--i: 5;">
        <div class="in-tabs" role="tablist" aria-label="Filter users">
            @foreach ($tabs as $key => $label)
                <button type="button" class="in-tab {{ $key === 'all' ? 'active' : '' }}" data-filter="{{ $key }}" role="tab" aria-selected="{{ $key === 'all' ? 'true' : 'false' }}">
                    {{ $label }} <span class="in-tab-count">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </div>
        <div class="in-search">
            <i class="bi bi-search"></i>
            <input type="search" id="user-search" class="form-control" placeholder="Search name or email" aria-label="Search users">
        </div>
    </div>

    <div class="ad-panel h-auto db-in" style="--i: 6;">
        <div class="ad-table-wrap">
            <table class="table ad-table ad-rtable mb-0 align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th class="text-center">Instances</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php
                            $isPaid = $user->subscription->plan !== 'free';
                            $groups = [$isPaid ? 'paid' : 'free'];
                            if ($user->is_suspended) $groups[] = 'suspended';
                            if (! $user->email_verified_at) $groups[] = 'unverified';
                        @endphp
                        <tr data-user-row data-groups="{{ implode(' ', $groups) }}"
                            data-search="{{ Str::lower($user->name.' '.$user->email) }}">
                            <td class="ad-cell-user">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="ad-avatar ad-tone-{{ $avatarTones[$user->id % count($avatarTones)] }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <div style="min-width: 0;">
                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                            <span class="fw-semibold text-truncate">{{ $user->name }}</span>
                                            @if ($user->is_admin)
                                                <span class="ad-pill ad-tone-grey">Admin</span>
                                            @endif
                                        </div>
                                        <div class="text-muted small text-truncate" title="{{ $user->email }}">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Plan">
                                <span class="ad-pill {{ $isPaid ? 'ad-tone-green' : 'ad-tone-grey' }}">
                                    @if ($isPaid)<i class="bi bi-gem"></i>@endif
                                    {{ $user->subscription->planDetails()['name'] }}
                                </span>
                            </td>
                            <td data-label="Instances" class="text-lg-center">
                                <span class="fw-semibold">{{ $user->whatsapp_sessions_count }}</span>
                            </td>
                            <td data-label="Status">
                                @if ($user->is_suspended)
                                    <span class="ad-pill ad-tone-red"><i class="bi bi-slash-circle"></i>Suspended</span>
                                @elseif (! $user->email_verified_at)
                                    <span class="ad-pill ad-tone-amber"><i class="bi bi-envelope"></i>Unverified</span>
                                @else
                                    <span class="ad-pill ad-tone-green"><i class="bi bi-check-circle"></i>Active</span>
                                @endif
                            </td>
                            <td data-label="Joined">
                                <div>{{ $user->created_at->format('M j, Y') }}</div>
                                <div class="text-muted small">{{ $user->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="ad-cell-action text-end">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                    View <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="text-center text-muted py-5 d-none" data-user-none>
            <i class="bi bi-search fs-2 d-block mb-2"></i>
            No users match this filter.
        </div>
    </div>

    <script>
    // Filter tabs + search, all client-side (every user is already on the page).
    (function () {
        const rows = document.querySelectorAll('[data-user-row]');
        const tabs = document.querySelectorAll('[data-filter]');
        const search = document.getElementById('user-search');
        const none = document.querySelector('[data-user-none]');
        let filter = 'all';

        function apply() {
            const term = search.value.trim().toLowerCase();
            let shown = 0;

            rows.forEach((row) => {
                const visible = (filter === 'all' || row.dataset.groups.split(' ').includes(filter))
                    && (!term || row.dataset.search.includes(term));
                row.classList.toggle('d-none', !visible);
                shown += visible ? 1 : 0;
            });

            none.classList.toggle('d-none', shown > 0);
        }

        tabs.forEach((tab) => tab.addEventListener('click', () => {
            filter = tab.dataset.filter;
            tabs.forEach((t) => {
                t.classList.toggle('active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });
            apply();
        }));

        search.addEventListener('input', apply);
    })();
    </script>
@endif
@endsection
