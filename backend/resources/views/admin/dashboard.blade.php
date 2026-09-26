@extends('layouts.app')

@section('title', 'Admin · Dashboard')

@section('content')
@php
    $signupDiff = $signupsThisMonth - $signupsLastMonth;
    $connectedPct = $totalInstances > 0 ? round($connectedInstances / $totalInstances * 100) : 0;
    $paidPct = $totalUsers > 0 ? round($paidUsers / $totalUsers * 100) : 0;
    $messageTotal = $sentCount + $receivedCount + $failedCount;
    $maxSignups = max(1, max($signupsByMonth));
    // One colour per paid plan, in price order; free plans are always grey.
    $planTones = ['green', 'blue', 'purple', 'amber'];
    $paidIndex = 0;
@endphp

{{-- Hero --}}
<div class="db-hero db-in ad-hero rounded-4 p-4 p-md-5 mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4">
    <span class="db-orb db-orb-1" aria-hidden="true"></span>
    <span class="db-orb db-orb-2" aria-hidden="true"></span>

    <div>
        <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin console</span>
        <h1 class="h3 mt-2 mb-1">Platform overview</h1>
        <p class="text-muted mb-0">Platform-wide overview — counts only, never message content.</p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <span class="ad-chip"><span class="db-live {{ $connectedInstances > 0 ? 'is-on' : '' }}" aria-hidden="true"></span>{{ $connectedInstances }} live {{ Str::plural('session', $connectedInstances) }}</span>
            <span class="ad-chip"><i class="bi bi-calendar3"></i>{{ now()->format('l, j M Y') }}</span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="{{ route('admin.users.index') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 ad-btn-lift">
            <i class="bi bi-people"></i> Manage users
        </a>
        <a href="{{ route('admin.plans.index') }}" class="btn btn-light d-inline-flex align-items-center gap-2 ad-btn-lift">
            <i class="bi bi-credit-card"></i> Plans
        </a>
    </div>
</div>

{{-- Platform --}}
<div class="ad-section-title db-in" style="--i: 1;"><i class="bi bi-hdd-network"></i> Platform</div>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 2;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Users</span>
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-people"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $totalUsers }}">{{ $totalUsers }}</div>
            <div class="ad-stat-note">{{ $paidUsers }} on a paid plan</div>
            <div class="db-progress" role="progressbar" aria-label="Users on a paid plan"
                 aria-valuenow="{{ $paidPct }}" aria-valuemin="0" aria-valuemax="100">
                <span style="--pct: {{ $paidPct }}%;"></span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 3;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Instances</span>
                <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-hdd-stack"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $totalInstances }}">{{ $totalInstances }}</div>
            <div class="ad-stat-note">
                <span class="db-live {{ $connectedInstances > 0 ? 'is-on' : '' }}" aria-hidden="true"></span>{{ $connectedInstances }} currently connected
            </div>
            <div class="db-progress" role="progressbar" aria-label="Connected instances"
                 aria-valuenow="{{ $connectedPct }}" aria-valuemin="0" aria-valuemax="100">
                <span style="--pct: {{ $connectedPct }}%;"></span>
            </div>
            <a href="{{ route('admin.instances.index') }}" class="ad-stat-link">View all <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 4;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Messages this month</span>
                <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-chat-left-text"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $messageTotal }}">{{ $messageTotal }}</div>
            {{-- Stacked bar: each part's share of this month's messages. --}}
            <div class="ad-stack" aria-hidden="true">
                @if ($messageTotal > 0)
                    <span class="ad-stack-sent" style="width: {{ $sentCount / $messageTotal * 100 }}%;"></span>
                    <span class="ad-stack-received" style="width: {{ $receivedCount / $messageTotal * 100 }}%;"></span>
                    <span class="ad-stack-failed" style="width: {{ $failedCount / $messageTotal * 100 }}%;"></span>
                @endif
            </div>
            <ul class="ad-legend">
                <li><span class="ad-dot ad-stack-sent"></span>{{ $sentCount }} sent</li>
                <li><span class="ad-dot ad-stack-received"></span>{{ $receivedCount }} received</li>
                <li class="{{ $failedCount > 0 ? 'text-danger fw-semibold' : '' }}"><span class="ad-dot ad-stack-failed"></span>{{ $failedCount }} failed</li>
            </ul>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 5;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Plans</span>
                <span class="ad-stat-icon ad-tone-amber"><i class="bi bi-layers"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $planBreakdown->count() }}">{{ $planBreakdown->count() }}</div>
            <div class="ad-stat-note">{{ $planBreakdown->where('price', '>', 0)->count() }} paid &middot; {{ $planBreakdown->where('price', '<=', 0)->count() }} free</div>
            <a href="{{ route('admin.plans.index') }}" class="ad-stat-link">Manage plans <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</div>

{{-- Business --}}
<div class="ad-section-title db-in" style="--i: 6;"><i class="bi bi-briefcase"></i> Business</div>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat ad-stat-feature db-in" style="--i: 7;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Monthly revenue (est.)</span>
                <span class="ad-stat-icon"><i class="bi bi-currency-rupee"></i></span>
            </div>
            <div class="ad-stat-value">₹{{ number_format($mrr) }}</div>
            <div class="ad-stat-note" title="Active paid subscriptions × each plan's current price. Someone who subscribed before a price change may pay a different amount.">
                From active paid plans <i class="bi bi-info-circle"></i>
            </div>
            <a href="{{ route('admin.revenue.index') }}" class="ad-stat-link">View revenue <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 8;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Paying customers</span>
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-person-check"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $payingCustomers }}">{{ $payingCustomers }}</div>
            <div class="ad-stat-note">
                @if ($pendingPayments > 0)
                    <span class="ad-pill ad-tone-amber"><i class="bi bi-hourglass-split"></i>{{ $pendingPayments }} pending payment</span>
                @else
                    No pending payments
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 9;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Sign-ups this month</span>
                <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-person-plus"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $signupsThisMonth }}">{{ $signupsThisMonth }}</div>
            <div class="ad-stat-note">
                @if ($signupDiff > 0)
                    <span class="ad-pill ad-tone-green"><i class="bi bi-arrow-up-short"></i>{{ $signupDiff }} more than last month</span>
                @elseif ($signupDiff < 0)
                    <span class="ad-pill ad-tone-red"><i class="bi bi-arrow-down-short"></i>{{ abs($signupDiff) }} fewer than last month</span>
                @else
                    <span class="ad-pill ad-tone-grey">Same as last month</span>
                @endif
                <span class="ms-1">({{ $signupsLastMonth }})</span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="ad-stat db-in" style="--i: 10;">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Unverified sign-ups</span>
                <span class="ad-stat-icon {{ $unverifiedUsers > 0 ? 'ad-tone-amber' : 'ad-tone-grey' }}"><i class="bi bi-envelope-exclamation"></i></span>
            </div>
            <div class="ad-stat-value" data-count-up="{{ $unverifiedUsers }}">{{ $unverifiedUsers }}</div>
            <div class="ad-stat-note">Never clicked the email link</div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Sign-ups per month --}}
    <div class="col-lg-5">
        <div class="ad-panel db-in" style="--i: 11;">
            <div class="ad-panel-head">
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-bar-chart-line"></i></span>
                <div>
                    <div class="fw-semibold">Sign-ups, last 6 months</div>
                    <div class="text-muted small">{{ array_sum($signupsByMonth) }} in total</div>
                </div>
            </div>
            <div class="ad-bars">
                @foreach ($signupsByMonth as $month => $count)
                    <div class="ad-bar {{ $loop->last ? 'is-current' : '' }}" style="--h: {{ round($count / $maxSignups * 100) }}%; --i: {{ $loop->index }};"
                         role="img" aria-label="{{ $month }}: {{ $count }} sign-ups" title="{{ $month }}: {{ $count }}">
                        <span class="ad-bar-value">{{ $count }}</span>
                        <span class="ad-bar-track"><span class="ad-bar-fill"></span></span>
                        <span class="ad-bar-label">{{ substr($month, 0, 3) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Plans --}}
    <div class="col-lg-7">
        <div class="ad-panel db-in" style="--i: 12;">
            <div class="ad-panel-head">
                <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-pie-chart"></i></span>
                <div>
                    <div class="fw-semibold">Users per plan</div>
                    <div class="text-muted small">Revenue uses each plan's current price</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table ad-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th class="d-none d-sm-table-cell">Price</th>
                            <th class="text-center">Users</th>
                            <th class="text-center">Active</th>
                            <th class="text-end">Revenue/month</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($planBreakdown as $plan)
                            @php
                                $tone = $plan->price > 0 ? $planTones[$paidIndex++ % count($planTones)] : 'grey';
                                $share = $mrr > 0 ? round($plan->monthly_revenue / $mrr * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <span class="d-flex align-items-center gap-2">
                                        <span class="ad-dot ad-dot-{{ $tone }}"></span>
                                        <span>
                                            <span class="d-block fw-semibold">{{ $plan->name }}</span>
                                            <span class="d-block d-sm-none text-muted small">{{ $plan->price > 0 ? '₹'.number_format($plan->price).'/mo' : 'Free' }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="d-none d-sm-table-cell text-muted">{{ $plan->price > 0 ? '₹'.number_format($plan->price).'/mo' : 'Free' }}</td>
                                <td class="text-center">{{ $plan->subscriptions_count }}</td>
                                <td class="text-center">{{ $plan->active_subscriptions_count }}</td>
                                <td class="text-end">
                                    @if ($plan->price > 0)
                                        <span class="fw-semibold">₹{{ number_format($plan->monthly_revenue) }}</span>
                                        <span class="ad-share" title="{{ $share }}% of revenue"><span class="ad-dot-{{ $tone }}" style="width: {{ $share }}%;"></span></span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total (est.)</td>
                            <td class="d-none d-sm-table-cell"></td>
                            <td></td>
                            <td></td>
                            <td class="text-end">₹{{ number_format($mrr) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
