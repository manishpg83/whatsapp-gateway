@extends('layouts.app')

@section('title', 'Billing')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="mb-4">
            <h1 class="h3 mb-1">Billing</h1>
            <p class="text-muted mb-0">Manage your plan, track your usage, and upgrade anytime.</p>
        </div>

        {{-- Current plan + usage --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
                    <i class="bi bi-credit-card"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase">Current plan</div>
                            <div class="h5 mb-0">{{ $subscription->planDetails()['name'] }}</div>
                        </div>
                        @if ($subscription->plan !== 'free')
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill text-bg-{{ $subscription->status === 'active' ? 'success' : 'secondary' }}">
                                    <i class="bi bi-circle-fill me-1" style="font-size: .5rem;"></i>{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                                </span>
                                <form method="POST" action="{{ route('billing.cancel') }}"
                                      onsubmit="return confirm('Cancel your subscription? You will be moved to the Free plan immediately. Already-charged amounts for this period are not refunded.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel subscription</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                @php
                    $instanceLimit = $subscription->planDetails()['instances'];
                    $messageLimit = $subscription->planDetails()['messages_per_month'];
                    $instancePct = min(100, $instanceCount / max(1, $instanceLimit) * 100);
                    $messagePct = min(100, $messageCount / max(1, $messageLimit) * 100);
                @endphp
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span><i class="bi bi-hdd-stack me-1"></i>Instances</span>
                            <span>{{ $instanceCount }} / {{ $instanceLimit }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar {{ $instancePct >= 90 ? 'bg-danger' : '' }}" style="width: {{ $instancePct }}%"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span><i class="bi bi-chat-left-text me-1"></i>Messages this month</span>
                            <span>{{ $messageCount }} / {{ number_format($messageLimit) }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar {{ $messagePct >= 90 ? 'bg-danger' : '' }}" style="width: {{ $messagePct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Plans --}}
        <div class="row g-3 align-items-stretch">
            @foreach ($plans as $key => $plan)
                @php
                    $isCurrent = $subscription->plan === $key;
                    $isPopular = ! empty($plan['popular']);
                    $tierIcon = match ($key) {
                        'free' => 'bi-gift',
                        'starter' => 'bi-lightning-charge',
                        'growth' => 'bi-graph-up-arrow',
                        default => 'bi-building',
                    };
                @endphp
                <div class="col-md-3">
                    <div class="card shadow-sm h-100 position-relative {{ $isPopular ? 'border-primary border-2' : '' }} {{ $isCurrent ? 'bg-wa-light' : '' }}">
                        @if ($isPopular)
                            <div class="badge text-bg-primary rounded-pill position-absolute top-0 start-50 translate-middle">Most popular</div>
                        @endif
                        <div class="card-body d-flex flex-column">
                            <div class="text-primary fs-4 mb-2"><i class="bi {{ $tierIcon }}"></i></div>
                            <div class="fw-semibold">{{ $plan['name'] }}</div>
                            <p class="text-muted small mb-2">{{ $plan['description'] }}</p>
                            <div class="h4 mb-3">
                                @if ($plan['price'] > 0)
                                    &#8377;{{ number_format($plan['price']) }}<span class="fs-6 text-muted">/mo</span>
                                @else
                                    Free
                                @endif
                            </div>
                            <ul class="list-unstyled small mb-3">
                                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>{{ $plan['instances'] }} instance{{ $plan['instances'] > 1 ? 's' : '' }}</li>
                                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>{{ number_format($plan['messages_per_month']) }} messages/mo</li>
                                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Full REST API access</li>
                                <li class="mb-1"><i class="bi bi-check-circle-fill text-primary me-1"></i>Webhook delivery</li>
                            </ul>

                            <div class="mt-auto">
                                @if ($isCurrent)
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

        <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Cashfree is in <strong>sandbox/test mode</strong> — no real money moves. Upgrading opens Cashfree's own checkout page to authorize a test payment.
        </p>
    </div>
</div>
@endsection
