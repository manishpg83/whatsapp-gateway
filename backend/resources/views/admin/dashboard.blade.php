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
                <div class="text-muted small">{{ $connectedInstances }} currently connected</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Messages sent</div>
                <div class="display-6 fw-semibold">{{ $messagesThisMonth }}</div>
                <div class="text-muted small">This calendar month, all users</div>
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

<div class="card shadow-sm">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-pie-chart"></i>
        </div>
        <div class="fw-semibold">Users per plan</div>
    </div>
    <table class="table table-hover mb-0 align-middle">
        <thead>
            <tr>
                <th>Plan</th>
                <th>Price</th>
                <th>Users</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($planBreakdown as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td>{{ $plan->price > 0 ? '₹'.number_format($plan->price).'/mo' : 'Free' }}</td>
                    <td>{{ $plan->subscriptions_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
