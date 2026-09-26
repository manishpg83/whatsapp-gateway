@extends('layouts.app')

@section('title', 'Admin · Revenue')

@section('content')
@php
    $tones = [
        'active' => 'green',
        'pending' => 'amber',
        'past_due' => 'red',
        'cancelled' => 'grey',
    ];
    $icons = [
        'active' => 'bi-check-circle',
        'pending' => 'bi-hourglass-split',
        'past_due' => 'bi-exclamation-circle',
        'cancelled' => 'bi-x-circle',
    ];
    $atRisk = $statusCounts['pending'] + $statusCounts['past_due'];
    $totalSubs = array_sum($statusCounts);
    $avatarTones = ['green', 'blue', 'purple', 'amber'];

    // One colour per paid plan, in price order (same order as the dashboard).
    $planColors = ['green' => '#10b981', 'blue' => '#3b82f6', 'purple' => '#8b5cf6', 'amber' => '#f59e0b'];
    $planTone = [];
    foreach ($plans->values() as $i => $plan) {
        $planTone[$plan->slug] = array_keys($planColors)[$i % count($planColors)];
    }

    // Donut slices as a conic-gradient: each plan's share of MRR.
    $stops = [];
    $at = 0;
    foreach ($plans as $plan) {
        if ($mrr > 0 && $plan->monthly_revenue > 0) {
            $end = $at + $plan->monthly_revenue / $mrr * 100;
            $stops[] = $planColors[$planTone[$plan->slug]].' '.round($at, 2).'% '.round($end, 2).'%';
            $at = $end;
        }
    }
    $donut = $stops ? 'conic-gradient('.implode(', ', $stops).')' : 'conic-gradient(#e9eeec 0 100%)';
@endphp

{{-- Header --}}
<div class="mb-4 db-in">
    <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin</span>
    <h1 class="h3 mt-2 mb-1">Revenue</h1>
    <p class="text-muted mb-0">
        Who is paying, how much, and what's at risk. Figures are estimates: active paid subscriptions × each plan's current price.
    </p>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat ad-stat-feature db-in" style="--i: 1;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">MRR (est.)</span>
                <span class="ad-stat-icon"><i class="bi bi-currency-rupee"></i></span>
            </div>
            <div class="ad-stat-value">₹{{ number_format($mrr) }}</div>
            <div class="ad-stat-note">Per month</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 2;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Paying customers</span>
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-person-check"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $statusCounts['active'] }}">{{ $statusCounts['active'] }}</div>
            <div class="ad-stat-note">Active paid plans</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 3;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">At risk</span>
                <span class="ad-stat-icon {{ $atRisk > 0 ? 'ad-tone-amber' : 'ad-tone-grey' }}"><i class="bi bi-exclamation-triangle"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $atRisk }}">{{ $atRisk }}</div>
            <div class="ad-stat-note">{{ $statusCounts['pending'] }} pending &middot; {{ $statusCounts['past_due'] }} past due</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 4;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Cancelled</span>
                <span class="ad-stat-icon ad-tone-grey"><i class="bi bi-person-dash"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $statusCounts['cancelled'] }}">{{ $statusCounts['cancelled'] }}</div>
            <div class="ad-stat-note">Churned subscriptions</div>
        </div>
    </div>
</div>

{{-- Revenue by plan --}}
<div class="ad-panel h-auto mb-4 db-in" style="--i: 5;">
    <div class="ad-panel-head">
        <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-pie-chart"></i></span>
        <div>
            <div class="fw-semibold">Revenue by plan</div>
            <div class="text-muted small">Share of monthly revenue from each paid plan</div>
        </div>
    </div>
    <div class="row g-0 align-items-center">
        <div class="col-lg-4 d-flex justify-content-center py-4 ad-donut-col">
            <div class="ad-donut" style="--donut: {{ $donut }};" role="img"
                 aria-label="{{ $plans->map(fn ($p) => $p->name.' ₹'.number_format($p->monthly_revenue))->implode(', ') }}">
                <div class="ad-donut-hole">
                    <span class="ad-stat-label">Per month</span>
                    <span class="ad-donut-value">₹{{ number_format($mrr) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="table-responsive">
                <table class="table ad-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th class="d-none d-sm-table-cell">Price</th>
                            <th class="text-center"><span class="d-none d-sm-inline">Active subscribers</span><span class="d-sm-none">Active</span></th>
                            <th class="text-end"><span class="d-none d-sm-inline">Revenue/month</span><span class="d-sm-none">Revenue</span></th>
                            <th class="d-none d-md-table-cell" style="width: 28%;">Share of MRR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            @php $share = $mrr > 0 ? round($plan->monthly_revenue / $mrr * 100) : 0; @endphp
                            <tr>
                                <td>
                                    <span class="d-flex align-items-center gap-2">
                                        <span class="ad-dot ad-dot-{{ $planTone[$plan->slug] }}"></span>
                                        <span>
                                            <span class="d-block fw-semibold">{{ $plan->name }}</span>
                                            <span class="d-block d-sm-none text-muted small">₹{{ number_format($plan->price) }}/mo</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="d-none d-sm-table-cell text-muted">₹{{ number_format($plan->price) }}/mo</td>
                                <td class="text-center">{{ $plan->active_subscriptions_count }}</td>
                                <td class="text-end fw-semibold">
                                    ₹{{ number_format($plan->monthly_revenue) }}
                                    <span class="d-md-none d-block small text-muted fw-normal">{{ $share }}%</span>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="ad-share-bar flex-grow-1" role="progressbar" aria-label="{{ $plan->name }} share of MRR"
                                             aria-valuenow="{{ $share }}" aria-valuemin="0" aria-valuemax="100">
                                            <span class="ad-dot-{{ $planTone[$plan->slug] }}" style="width: {{ $share }}%;"></span>
                                        </div>
                                        <span class="small text-muted text-end" style="width: 36px;">{{ $share }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="d-none d-sm-table-cell"></td>
                            <td></td>
                            <td class="text-end">₹{{ number_format($mrr) }}</td>
                            <td class="d-none d-md-table-cell"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Subscriptions --}}
<div class="ad-section-title db-in" style="--i: 6;"><i class="bi bi-receipt"></i> Paid subscriptions</div>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-3">
    <div class="col">
        <a href="{{ route('admin.revenue.index', array_filter(['search' => $search])) }}"
           class="ad-filter db-in {{ $status === null ? 'active' : '' }}" style="--i: 7;"
           @if ($status === null) aria-current="page" @endif>
            <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-receipt"></i></span>
            <span>
                <span class="ad-stat-label d-block">All</span>
                <span class="ad-mini-value d-block">{{ $totalSubs }}</span>
            </span>
        </a>
    </div>
    @foreach ($statuses as $value => $label)
        <div class="col">
            <a href="{{ route('admin.revenue.index', array_filter(['status' => $value, 'search' => $search])) }}"
               class="ad-filter ad-filter-{{ $tones[$value] }} db-in {{ $status === $value ? 'active' : '' }}" style="--i: {{ $loop->iteration + 7 }};"
               @if ($status === $value) aria-current="page" @endif>
                <span class="ad-stat-icon ad-tone-{{ $tones[$value] }}"><i class="bi {{ $icons[$value] }}"></i></span>
                <span>
                    <span class="ad-stat-label d-block">{{ $label }}</span>
                    <span class="ad-mini-value d-block">{{ $statusCounts[$value] }}</span>
                </span>
            </a>
        </div>
    @endforeach
</div>

@if ($totalSubs > 0)
    <div class="ad-health mb-4 db-in" style="--i: 12;" role="img"
         aria-label="{{ collect($statuses)->map(fn ($label, $key) => $statusCounts[$key].' '.$label)->implode(', ') }}">
        @foreach ($statuses as $value => $label)
            @if ($statusCounts[$value] > 0)
                <span class="ad-dot-{{ $tones[$value] }}" style="width: {{ $statusCounts[$value] / $totalSubs * 100 }}%;" title="{{ $label }}: {{ $statusCounts[$value] }}"></span>
            @endif
        @endforeach
    </div>
@endif

<form method="GET" action="{{ route('admin.revenue.index') }}" class="d-flex align-items-center gap-2 mb-3 db-in" style="--i: 13;">
    @if ($status)
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="in-search ad-search">
        <i class="bi bi-search"></i>
        <input type="search" name="search" value="{{ $search }}" class="form-control"
               placeholder="Search customer name or email" aria-label="Search">
    </div>
    <button type="submit" class="btn btn-primary ad-btn-lift">Search</button>
    @if ($search !== '' || $status)
        <a href="{{ route('admin.revenue.index') }}" class="btn btn-light border text-nowrap"><i class="bi bi-x-lg me-1"></i>Clear</a>
    @endif
</form>

@if ($subscriptions->isEmpty())
    <div class="ad-panel h-auto text-center text-muted py-5">
        <i class="bi bi-receipt fs-1 d-block mb-2"></i>
        {{ $search !== '' || $status ? 'No subscriptions match these filters.' : 'No paid subscriptions yet.' }}
    </div>
@else
    <div class="ad-panel h-auto db-in" style="--i: 14;">
        <div class="ad-table-wrap">
            <table class="table ad-table ad-rtable mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th class="text-lg-end">Amount/month</th>
                        <th>Renews on</th>
                        <th title="When this user registered (we don't record when they first paid)">Customer since</th>
                        <th>Cashfree ID</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        @php
                            $plan = $paidPlans[$subscription->plan];
                            $customer = $subscription->user;
                        @endphp
                        <tr>
                            <td class="ad-cell-user">
                                <span class="d-flex align-items-center gap-2" style="min-width: 0;">
                                    <span class="ad-avatar ad-avatar-sm ad-tone-{{ $avatarTones[$customer->id % count($avatarTones)] }}">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                                    <span style="min-width: 0;">
                                        <a href="{{ route('admin.users.show', $customer) }}" class="fw-semibold text-decoration-none d-block text-truncate ad-owner">{{ $customer->name }}</a>
                                        <span class="text-muted small d-block text-truncate ad-owner" title="{{ $customer->email }}">{{ $customer->email }}</span>
                                    </span>
                                </span>
                            </td>
                            <td data-label="Plan">
                                <span class="d-inline-flex align-items-center gap-2 text-nowrap">
                                    <span class="ad-dot ad-dot-{{ $planTone[$subscription->plan] ?? 'grey' }}"></span>{{ $plan->name }}
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="ad-pill ad-tone-{{ $tones[$subscription->status] ?? 'grey' }}">
                                    <i class="bi {{ $icons[$subscription->status] ?? 'bi-circle' }}"></i>
                                    {{ $statuses[$subscription->status] ?? ucfirst($subscription->status) }}
                                </span>
                            </td>
                            <td data-label="Amount/month" class="text-lg-end fw-semibold">₹{{ number_format($plan->price) }}</td>
                            <td data-label="Renews on" class="text-nowrap small">{{ $subscription->current_period_end?->format('Y-m-d') ?? '—' }}</td>
                            <td data-label="Customer since" class="text-nowrap small">{{ $customer->created_at->format('Y-m-d') }}</td>
                            <td data-label="Cashfree ID" class="ad-cell-span2">
                                <code class="small ad-code">{{ $subscription->cashfree_subscription_id ?? '—' }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $subscriptions->links() }}
    </div>
@endif
@endsection
