@extends('layouts.app')

@section('title', 'Admin · Revenue')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Revenue</h1>
    <p class="text-muted mb-0">
        Who is paying, how much, and what's at risk. Figures are estimates: active paid subscriptions × each plan's current price.
    </p>
</div>

@php
    $statusColors = [
        'active' => 'success',
        'pending' => 'warning',
        'past_due' => 'danger',
        'cancelled' => 'secondary',
    ];
    $atRisk = $statusCounts['pending'] + $statusCounts['past_due'];
@endphp

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-md col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">MRR <span class="text-lowercase">(est.)</span></div>
                <div class="fs-3 fw-semibold">₹{{ number_format($mrr) }}</div>
                <div class="text-muted small">Per month</div>
            </div>
        </div>
    </div>
    <div class="col-md col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Paying customers</div>
                <div class="fs-3 fw-semibold">{{ $statusCounts['active'] }}</div>
                <div class="text-muted small">Active paid plans</div>
            </div>
        </div>
    </div>
    <div class="col-md col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--bs-warning);">
            <div class="card-body">
                <div class="text-muted small text-uppercase">At risk</div>
                <div class="fs-3 fw-semibold">{{ $atRisk }}</div>
                <div class="text-muted small">{{ $statusCounts['pending'] }} pending &middot; {{ $statusCounts['past_due'] }} past due</div>
            </div>
        </div>
    </div>
    <div class="col-md col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--bs-secondary);">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Cancelled</div>
                <div class="fs-3 fw-semibold">{{ $statusCounts['cancelled'] }}</div>
                <div class="text-muted small">Churned subscriptions</div>
            </div>
        </div>
    </div>
</div>

{{-- Revenue by plan --}}
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-pie-chart"></i>
        </div>
        <div class="fw-semibold">Revenue by plan</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Active subscribers</th>
                    <th class="text-end">Revenue/month</th>
                    <th style="width: 30%;">Share of MRR</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plans as $plan)
                    @php $share = $mrr > 0 ? round($plan->monthly_revenue / $mrr * 100) : 0; @endphp
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td>₹{{ number_format($plan->price) }}/mo</td>
                        <td>{{ $plan->active_subscriptions_count }}</td>
                        <td class="text-end">₹{{ number_format($plan->monthly_revenue) }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 8px;" role="progressbar" aria-label="{{ $plan->name }} share of MRR"
                                     aria-valuenow="{{ $share }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $share }}%"></div>
                                </div>
                                <span class="small text-muted" style="width: 36px;">{{ $share }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td colspan="3">Total</td>
                    <td class="text-end">₹{{ number_format($mrr) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Subscriptions --}}
<h2 class="h5 mb-3">Paid subscriptions</h2>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.revenue.index', array_filter(['search' => $search])) }}"
       class="btn btn-sm {{ $status === null ? 'btn-dark' : 'btn-outline-dark' }}">
        All <span class="badge text-bg-light ms-1">{{ array_sum($statusCounts) }}</span>
    </a>
    @foreach ($statuses as $value => $label)
        <a href="{{ route('admin.revenue.index', array_filter(['status' => $value, 'search' => $search])) }}"
           class="btn btn-sm {{ $status === $value ? 'btn-'.$statusColors[$value] : 'btn-outline-'.$statusColors[$value] }}">
            {{ $label }} <span class="badge text-bg-light ms-1">{{ $statusCounts[$value] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.revenue.index') }}" class="row g-2 mb-3">
    @if ($status)
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="col-sm-8 col-md-6 col-lg-4">
        <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm"
               placeholder="Search customer name or email" aria-label="Search">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Search</button>
    </div>
    @if ($search !== '' || $status)
        <div class="col-auto">
            <a href="{{ route('admin.revenue.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear</a>
        </div>
    @endif
</form>

@if ($subscriptions->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            {{ $search !== '' || $status ? 'No subscriptions match these filters.' : 'No paid subscriptions yet.' }}
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th class="text-end">Amount/month</th>
                        <th>Renews on</th>
                        <th title="When this user registered (we don't record when they first paid)">Customer since</th>
                        <th>Cashfree ID</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        @php $plan = $paidPlans[$subscription->plan]; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $subscription->user) }}" class="text-decoration-none">{{ $subscription->user->name }}</a>
                                <div class="text-muted small">{{ $subscription->user->email }}</div>
                            </td>
                            <td>{{ $plan->name }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-{{ $statusColors[$subscription->status] ?? 'secondary' }}">
                                    {{ $statuses[$subscription->status] ?? ucfirst($subscription->status) }}
                                </span>
                            </td>
                            <td class="text-end">₹{{ number_format($plan->price) }}</td>
                            <td class="text-nowrap small">{{ $subscription->current_period_end?->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-nowrap small">{{ $subscription->user->created_at->format('Y-m-d') }}</td>
                            <td><code class="small">{{ $subscription->cashfree_subscription_id ?? '—' }}</code></td>
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
