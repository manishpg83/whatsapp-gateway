@extends('layouts.app')

@section('title', 'Instances')

@section('content')
@php
    // One place that turns a raw status into what the card shows.
    // group = which filter tab the card belongs to.
    $describe = function ($instance) {
        return match (true) {
            $instance->status === 'connected' => ['label' => 'Connected', 'tone' => 'green', 'group' => 'connected', 'icon' => 'bi-check-circle-fill'],
            $instance->status === 'connecting' && $instance->phone_number => ['label' => 'Reconnecting', 'tone' => 'amber', 'group' => 'waiting', 'icon' => 'bi-arrow-repeat'],
            $instance->status === 'qr_pending' => ['label' => 'Waiting for QR scan', 'tone' => 'amber', 'group' => 'waiting', 'icon' => 'bi-qr-code'],
            $instance->status === 'connecting' => ['label' => 'Connecting', 'tone' => 'amber', 'group' => 'waiting', 'icon' => 'bi-arrow-repeat'],
            $instance->status === 'logged_out' => ['label' => 'Logged out', 'tone' => 'red', 'group' => 'offline', 'icon' => 'bi-box-arrow-right'],
            default => ['label' => 'Disconnected', 'tone' => 'grey', 'group' => 'offline', 'icon' => 'bi-pause-circle'],
        };
    };

    $counts = ['all' => $instances->count(), 'connected' => 0, 'waiting' => 0, 'offline' => 0];
    foreach ($instances as $instance) {
        $counts[$describe($instance)['group']]++;
    }

    $atLimit = $instances->count() >= $instanceLimit;
    $usagePct = $instanceLimit > 0 ? min(100, round($instances->count() / $instanceLimit * 100)) : 100;
@endphp

{{-- Header --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 db-in">
    <div>
        <h1 class="h3 mb-1">Instances</h1>
        <p class="text-muted mb-0">Your WhatsApp connections. Each one is a separate number with its own API tokens.</p>
    </div>
    <a href="{{ route('instances.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 flex-shrink-0 in-btn-lift">
        <i class="bi bi-plus-lg"></i> New instance
    </a>
</div>

@if ($instances->isEmpty())
    {{-- Empty state --}}
    <div class="card shadow-sm in-empty db-in" style="--i: 1;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi bi-whatsapp"></i></span>
            </div>
            <h2 class="h5 mb-2">No instances yet.</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 30rem;">
                An instance links one of your WhatsApp numbers to the gateway. Create one, scan the QR code with your phone, and you're ready to send.
            </p>
            <a href="{{ route('instances.create') }}" class="btn btn-primary btn-lg in-btn-lift"><i class="bi bi-plus-lg me-1"></i>Create your first instance</a>

            <div class="in-empty-steps">
                <span><i class="bi bi-plus-circle"></i> Create</span>
                <i class="bi bi-chevron-right text-muted small"></i>
                <span><i class="bi bi-qr-code-scan"></i> Scan QR</span>
                <i class="bi bi-chevron-right text-muted small"></i>
                <span><i class="bi bi-send"></i> Send via API</span>
            </div>
        </div>
    </div>
@else
    {{-- Summary strip --}}
    <div class="row g-3 mb-4">
        <div class="col-4 col-xl-3">
            <div class="in-stat db-in" style="--i: 1;">
                <span class="in-stat-icon d-none d-sm-inline-flex in-tone-green"><i class="bi bi-hdd-stack"></i></span>
                <div>
                    <div class="in-stat-label">Total</div>
                    <div class="in-stat-value" data-count-up="{{ $counts['all'] }}">{{ $counts['all'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-xl-3">
            <div class="in-stat db-in" style="--i: 2;">
                <span class="in-stat-icon d-none d-sm-inline-flex in-tone-green"><span class="db-live {{ $counts['connected'] ? 'is-on' : '' }} m-0"></span></span>
                <div>
                    <div class="in-stat-label">Connected</div>
                    <div class="in-stat-value" data-count-up="{{ $counts['connected'] }}">{{ $counts['connected'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-xl-3">
            <div class="in-stat db-in" style="--i: 3;">
                <span class="in-stat-icon d-none d-sm-inline-flex in-tone-{{ $counts['waiting'] + $counts['offline'] ? 'amber' : 'grey' }}"><i class="bi bi-exclamation-triangle"></i></span>
                <div>
                    <div class="in-stat-label">Need attention</div>
                    <div class="in-stat-value" data-count-up="{{ $counts['waiting'] + $counts['offline'] }}">{{ $counts['waiting'] + $counts['offline'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="in-stat db-in" style="--i: 4;">
                <span class="in-stat-icon in-tone-blue"><i class="bi bi-speedometer2"></i></span>
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <span class="in-stat-label">Plan usage</span>
                        <span class="small fw-semibold text-nowrap">{{ $instances->count() }} of {{ $instanceLimit }}</span>
                    </div>
                    <div class="db-progress mt-2" role="progressbar" aria-label="Instances used on your plan"
                         aria-valuenow="{{ $usagePct }}" aria-valuemin="0" aria-valuemax="100">
                        <span style="--pct: {{ $usagePct }}%;" @class(['in-progress-full' => $atLimit])></span>
                    </div>
                    @if ($atLimit)
                        <a href="{{ route('billing.index') }}" class="small text-decoration-none d-inline-block mt-1">Upgrade for more <i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Filters + search --}}
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3 db-in" style="--i: 5;">
        <div class="in-tabs" role="tablist" aria-label="Filter instances by status">
            @foreach (['all' => 'All', 'connected' => 'Connected', 'waiting' => 'Connecting', 'offline' => 'Offline'] as $key => $label)
                <button type="button" class="in-tab {{ $key === 'all' ? 'active' : '' }}" data-filter="{{ $key }}" role="tab" aria-selected="{{ $key === 'all' ? 'true' : 'false' }}">
                    {{ $label }} <span class="in-tab-count">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </div>
        <div class="in-search">
            <i class="bi bi-search"></i>
            <label for="instance-search" class="visually-hidden">Search instances</label>
            <input type="search" id="instance-search" class="form-control" placeholder="Search by name or number">
        </div>
    </div>

    {{-- Instance cards --}}
    <div class="row g-3" data-instance-grid>
        @foreach ($instances as $instance)
            @php $state = $describe($instance); @endphp
            <div class="col-md-6 col-xl-4" data-instance-card data-group="{{ $state['group'] }}"
                 data-search="{{ Str::lower($instance->name.' '.$instance->phone_number) }}">
                <div class="in-card in-card-{{ $state['tone'] }} db-in" style="--i: {{ min($loop->index + 6, 14) }};">
                    <div class="d-flex align-items-start gap-3">
                        <span class="in-card-avatar in-tone-{{ $state['tone'] }}"><i class="bi bi-whatsapp"></i></span>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <h2 class="in-card-name text-truncate" title="{{ $instance->name }}">{{ $instance->name }}</h2>
                            <span class="in-pill in-pill-{{ $state['tone'] }}">
                                <span class="in-pill-dot"></span>{{ $state['label'] }}
                            </span>
                        </div>
                        <i class="bi bi-arrow-up-right in-card-go" aria-hidden="true"></i>
                    </div>

                    <dl class="in-card-meta">
                        <div>
                            <dt><i class="bi bi-telephone"></i> Number</dt>
                            @if ($instance->phone_number)
                                <dd class="font-monospace">{{ $instance->phone_number }}</dd>
                            @else
                                <dd class="text-muted fw-normal">Not linked yet</dd>
                            @endif
                        </div>
                        <div>
                            @if ($instance->status === 'connected' && $instance->connected_at)
                                <dt><i class="bi bi-clock"></i> Online since</dt>
                                <dd title="{{ $instance->connected_at->format('Y-m-d H:i') }}">{{ $instance->connected_at->diffForHumans() }}</dd>
                            @else
                                <dt><i class="bi bi-clock"></i> Last update</dt>
                                <dd title="{{ $instance->updated_at->format('Y-m-d H:i') }}">{{ $instance->updated_at->diffForHumans() }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt><i class="bi bi-calendar3"></i> Created</dt>
                            <dd>{{ $instance->created_at->format('M j, Y') }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('instances.show', $instance) }}" class="in-card-link stretched-link">
                        View instance <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        @endforeach

        {{-- "Add another" tile (or an upgrade nudge at the plan limit). --}}
        <div class="col-md-6 col-xl-4" data-instance-add>
            @if ($atLimit)
                <a href="{{ route('billing.index') }}" class="in-add db-in" style="--i: {{ min($instances->count() + 6, 15) }};">
                    <span class="in-add-icon"><i class="bi bi-stars"></i></span>
                    <span class="fw-semibold">Need more numbers?</span>
                    <span class="small text-muted">You're using all {{ $instanceLimit }} on your plan. Upgrade to add more.</span>
                </a>
            @else
                <a href="{{ route('instances.create') }}" class="in-add db-in" style="--i: {{ min($instances->count() + 6, 15) }};">
                    <span class="in-add-icon"><i class="bi bi-plus-lg"></i></span>
                    <span class="fw-semibold">Add another number</span>
                    <span class="small text-muted">{{ $instanceLimit - $instances->count() }} more available on your plan</span>
                </a>
            @endif
        </div>
    </div>

    <div class="text-center text-muted py-5 d-none" data-instance-none>
        <i class="bi bi-search fs-3 d-block mb-2"></i>No instances match this filter.
    </div>

    <script>
    // Status tabs + search, all client-side (the list is already on the page).
    (function () {
        const cards = document.querySelectorAll('[data-instance-card]');
        const tabs = document.querySelectorAll('[data-filter]');
        const search = document.getElementById('instance-search');
        const addTile = document.querySelector('[data-instance-add]');
        const none = document.querySelector('[data-instance-none]');
        let filter = 'all';

        function apply() {
            const term = search.value.trim().toLowerCase();
            let shown = 0;

            cards.forEach((card) => {
                const visible = (filter === 'all' || card.dataset.group === filter)
                    && (!term || card.dataset.search.includes(term));
                card.classList.toggle('d-none', !visible);
                shown += visible ? 1 : 0;
            });

            // The "add" tile only makes sense on the unfiltered list.
            addTile.classList.toggle('d-none', filter !== 'all' || term !== '');
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
