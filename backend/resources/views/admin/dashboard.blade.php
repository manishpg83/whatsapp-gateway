@extends('layouts.app')

@section('title', 'Admin · Dashboard')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Admin</h1>
    <p class="text-muted mb-0">Platform-wide overview — counts only, never message content.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Users</div>
                <div class="display-6 fw-semibold">{{ $totalUsers }}</div>
                <div class="text-muted small">{{ $paidUsers }} on a paid plan</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-blue shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Instances</div>
                <div class="display-6 fw-semibold">{{ $totalInstances }}</div>
                <div class="text-muted small">{{ $connectedInstances }} currently connected &middot; <a href="{{ route('admin.instances.index') }}">View all</a></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Messages this month</div>
                <div class="text-muted small mt-1">
                    <i class="bi bi-arrow-up-short text-primary"></i>{{ $sentCount }} sent
                </div>
                <div class="text-muted small">
                    <i class="bi bi-arrow-down-short text-primary"></i>{{ $receivedCount }} received
                </div>
                <div class="text-muted small">
                    <i class="bi bi-exclamation-triangle{{ $failedCount > 0 ? '-fill text-danger' : '' }}"></i>
                    {{ $failedCount }} failed
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--wa-primary-dark);">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Plans</div>
                <div class="display-6 fw-semibold">{{ $planBreakdown->count() }}</div>
                <div class="text-muted small"><a href="{{ route('admin.plans.index') }}">Manage plans</a></div>
            </div>
        </div>
    </div>
</div>

{{-- Business --}}
<h2 class="h6 text-uppercase text-muted fw-semibold mb-3">Business</h2>
@php
    $signupDiff = $signupsThisMonth - $signupsLastMonth;
@endphp
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Monthly revenue <span class="text-lowercase">(est.)</span></div>
                <div class="display-6 fw-semibold">₹{{ number_format($mrr) }}</div>
                <div class="text-muted small" title="Active paid subscriptions × each plan's current price. Someone who subscribed before a price change may pay a different amount.">
                    From active paid plans <i class="bi bi-info-circle"></i>
                </div>
                <a href="{{ route('admin.revenue.index') }}" class="small">View revenue &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-blue shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Paying customers</div>
                <div class="display-6 fw-semibold">{{ $payingCustomers }}</div>
                <div class="text-muted small">
                    @if ($pendingPayments > 0)
                        <span class="text-warning-emphasis">{{ $pendingPayments }} pending payment</span>
                    @else
                        No pending payments
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Sign-ups this month</div>
                <div class="display-6 fw-semibold">{{ $signupsThisMonth }}</div>
                <div class="text-muted small">
                    @if ($signupDiff > 0)
                        <i class="bi bi-arrow-up-short text-success"></i>{{ $signupDiff }} more than last month
                    @elseif ($signupDiff < 0)
                        <i class="bi bi-arrow-down-short text-danger"></i>{{ abs($signupDiff) }} fewer than last month
                    @else
                        Same as last month
                    @endif
                    ({{ $signupsLastMonth }})
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--wa-primary-dark);">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Unverified sign-ups</div>
                <div class="display-6 fw-semibold">{{ $unverifiedUsers }}</div>
                <div class="text-muted small">Never clicked the email link</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Sign-ups per month --}}
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
                    <i class="bi bi-person-plus"></i>
                </div>
                <div class="fw-semibold">Sign-ups, last 6 months</div>
            </div>
            @php $maxSignups = max(1, max($signupsByMonth)); @endphp
            <table class="table mb-0 align-middle">
                <tbody>
                    @foreach ($signupsByMonth as $month => $count)
                        <tr>
                            <td class="text-nowrap small" style="width: 90px;">{{ $month }}</td>
                            <td>
                                <div class="progress" style="height: 8px;" role="progressbar" aria-label="{{ $month }} sign-ups"
                                     aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $maxSignups }}">
                                    <div class="progress-bar" style="width: {{ round($count / $maxSignups * 100) }}%"></div>
                                </div>
                            </td>
                            <td class="text-end fw-semibold small" style="width: 50px;">{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Plans --}}
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
                    <i class="bi bi-pie-chart"></i>
                </div>
                <div class="fw-semibold">Users per plan</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Price</th>
                            <th>Users</th>
                            <th>Active</th>
                            <th class="text-end">Revenue/month</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($planBreakdown as $plan)
                            <tr>
                                <td>{{ $plan->name }}</td>
                                <td>{{ $plan->price > 0 ? '₹'.number_format($plan->price).'/mo' : 'Free' }}</td>
                                <td>{{ $plan->subscriptions_count }}</td>
                                <td>{{ $plan->active_subscriptions_count }}</td>
                                <td class="text-end">{{ $plan->price > 0 ? '₹'.number_format($plan->monthly_revenue) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="4">Total (est.)</td>
                            <td class="text-end">₹{{ number_format($mrr) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
