@extends('layouts.app')

@section('title', 'Admin · Billing')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-credit-card me-2 text-primary"></i>Billing</h1>
        <p class="text-muted mb-0">Plans shown on the pricing page and enforced by the API.</p>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i>Add new plan
    </a>
</div>

<div class="card shadow-sm">
    <table class="table table-hover mb-0 align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Instances</th>
                <th>Messages/mo</th>
                <th>Subscribers</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr>
                    <td>
                        {{ $plan->name }}
                        @if ($plan->popular)
                            <span class="badge text-bg-primary rounded-pill ms-1">Most popular</span>
                        @endif
                        <div class="text-muted small">{{ $plan->description }}</div>
                    </td>
                    <td>{{ $plan->price > 0 ? '₹'.number_format($plan->price).'/mo' : 'Free' }}</td>
                    <td>{{ $plan->instances }}</td>
                    <td>{{ number_format($plan->messages_per_month) }}</td>
                    <td>{{ $plan->subscriptions_count }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="d-inline"
                              onsubmit="return confirm('Delete the {{ $plan->name }} plan? This only works if nobody is subscribed to it.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Editing a plan's price does not change what existing subscribers already pay — Cashfree plan
    objects are immutable, so a price change only applies to new subscriptions going forward.
</p>
@endsection
