@extends('layouts.app')

@section('title', 'Billing')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <h1 class="h3 mb-4">Billing</h1>

        {{-- Current plan + usage --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted small text-uppercase">Current plan</div>
                        <div class="h4 mb-0">{{ $subscription->planDetails()['name'] }}</div>
                    </div>
                    @if ($subscription->plan !== 'free')
                        <span class="badge text-bg-{{ $subscription->status === 'active' ? 'success' : 'secondary' }}">
                            {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                        </span>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Instances</span>
                            <span>{{ $instanceCount }} / {{ $subscription->planDetails()['instances'] }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" style="width: {{ min(100, $instanceCount / max(1, $subscription->planDetails()['instances']) * 100) }}%"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Messages this month</span>
                            <span>{{ $messageCount }} / {{ $subscription->planDetails()['messages_per_month'] }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" style="width: {{ min(100, $messageCount / max(1, $subscription->planDetails()['messages_per_month']) * 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Plans --}}
        <div class="row g-3">
            @foreach ($plans as $key => $plan)
                <div class="col-md-3">
                    <div class="card shadow-sm h-100 {{ $subscription->plan === $key ? 'border-primary' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="fw-semibold">{{ $plan['name'] }}</div>
                            <div class="h4 my-2">
                                @if ($plan['price'] > 0)
                                    &#8377;{{ number_format($plan['price']) }}<span class="fs-6 text-muted">/mo</span>
                                @else
                                    Free
                                @endif
                            </div>
                            <ul class="text-muted small mb-3 ps-3">
                                <li>{{ $plan['instances'] }} instance{{ $plan['instances'] > 1 ? 's' : '' }}</li>
                                <li>{{ number_format($plan['messages_per_month']) }} messages/mo</li>
                            </ul>

                            <div class="mt-auto">
                                @if ($subscription->plan === $key)
                                    <button class="btn btn-outline-secondary btn-sm w-100" disabled>Current plan</button>
                                @elseif ($plan['price'] > 0)
                                    <form method="POST" action="{{ route('billing.subscribe', $key) }}">
                                        @csrf
                                        <input type="text" name="phone" class="form-control form-control-sm mb-2 @error('phone') is-invalid @enderror"
                                               placeholder="Phone (919XXXXXXXXX)" required>
                                        @error('phone')
                                            <div class="invalid-feedback d-block small">{{ $message }}</div>
                                        @enderror
                                        <button type="submit" class="btn btn-primary btn-sm w-100">Upgrade</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-3">
            Cashfree is in <strong>sandbox/test mode</strong> — no real money moves. Upgrading opens Cashfree's own checkout page to authorize a test payment.
        </p>
    </div>
</div>
@endsection
