@extends('layouts.app')

@section('title', 'Admin · Billing')

@section('content')
@php
    $tierIcon = fn (string $slug) => match ($slug) {
        'free' => 'bi-gift',
        'starter' => 'bi-lightning-charge',
        'growth' => 'bi-graph-up-arrow',
        default => 'bi-building',
    };
    $paidCount = $plans->where('price', '>', 0)->count();
    $subscriberTotal = $plans->sum('subscriptions_count');
@endphp

{{-- Header --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4 db-in">
    <div>
        <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin</span>
        <h1 class="h3 mt-2 mb-1">Billing</h1>
        <p class="text-muted mb-0">Plans shown on the pricing page and enforced by the API.</p>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 ad-btn-lift flex-shrink-0">
        <i class="bi bi-plus-lg"></i>Add new plan
    </a>
</div>

{{-- Summary strip --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Plans', $plans->count(), 'bi-layers', 'purple'],
        ['Paid plans', $paidCount, 'bi-gem', 'green'],
        ['Subscribers', $subscriberTotal, 'bi-people', 'blue'],
    ] as [$label, $value, $icon, $tone])
        <div class="col-4">
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

{{-- Plan cards — the same card customers see on their Billing page --}}
<div class="row g-4 pt-2">
    @foreach ($plans as $plan)
        <div class="col-sm-6 col-xl-3">
            <div class="bl-plan {{ $plan->popular ? 'bl-plan-popular' : '' }} db-in" style="--i: {{ $loop->index + 4 }};">
                @if ($plan->popular)
                    <span class="bl-plan-badge"><i class="bi bi-star-fill"></i> Most popular</span>
                @endif

                <div class="d-flex justify-content-between align-items-start">
                    <span class="bl-plan-icon"><i class="bi {{ $tierIcon($plan->slug) }}"></i></span>
                    <code class="ad-slug" title="Slug — used by the API">{{ $plan->slug }}</code>
                </div>
                <div class="bl-plan-name">{{ $plan->name }}</div>
                <p class="bl-plan-desc">{{ $plan->description }}</p>
                <div class="bl-plan-price">
                    @if ($plan->price > 0)
                        &#8377;{{ number_format($plan->price) }}<span>/mo</span>
                    @else
                        Free
                    @endif
                </div>
                <ul class="bl-plan-list">
                    <li><i class="bi bi-check-lg"></i>{{ $plan->instances }} {{ Str::plural('instance', $plan->instances) }}</li>
                    <li><i class="bi bi-check-lg"></i>{{ number_format($plan->messages_per_month) }} messages/mo</li>
                </ul>

                <div class="ad-plan-subs">
                    <i class="bi bi-people"></i>
                    <span><strong>{{ $plan->subscriptions_count }}</strong> {{ Str::plural('subscriber', $plan->subscriptions_count) }}</span>
                </div>

                <div class="d-flex gap-2 mt-auto">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn bl-plan-btn btn-outline-primary flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}"
                          onsubmit="return confirm('Delete the {{ $plan->name }} plan? This only works if nobody is subscribed to it.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn bl-plan-btn btn-outline-danger px-3" aria-label="Delete {{ $plan->name }}" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Add tile --}}
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.plans.create') }}" class="ad-add-tile db-in" style="--i: {{ $plans->count() + 4 }};">
            <span class="ad-add-icon"><i class="bi bi-plus-lg"></i></span>
            <span class="fw-semibold">Add new plan</span>
            <span class="small text-muted">Set a price, instance limit and message quota</span>
        </a>
    </div>
</div>

<div class="bl-note db-in" style="--i: {{ $plans->count() + 5 }};">
    <i class="bi bi-info-circle"></i>
    <div>
        Editing a plan's price does not change what existing subscribers already pay — Cashfree plan
        objects are immutable, so a price change only applies to new subscriptions going forward.
    </div>
</div>
@endsection
