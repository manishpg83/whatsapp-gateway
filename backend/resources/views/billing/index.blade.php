@extends('layouts.app')

@section('title', 'Billing')

@section('content')
@php
    $current = $subscription->planDetails();
    $isPaid = $subscription->plan !== 'free';

    $instanceLimit = $current['instances'];
    $messageLimit = $current['messages_per_month'];
    $instancePct = (int) min(100, round($instanceCount / max(1, $instanceLimit) * 100));
    $messagePct = (int) min(100, round($messageCount / max(1, $messageLimit) * 100));
    $toneFor = fn (int $pct) => $pct >= 90 ? 'red' : ($pct >= 70 ? 'amber' : 'green');

    // Monthly message counts are per calendar month (PlanLimiter).
    $resetsOn = now()->addMonthNoOverflow()->startOfMonth();

    $tierIcon = fn (string $key) => match ($key) {
        'free' => 'bi-gift',
        'starter' => 'bi-lightning-charge',
        'growth' => 'bi-graph-up-arrow',
        default => 'bi-building',
    };

    $statusTone = match ($subscription->status) {
        'active' => 'green',
        'pending', 'initialized', 'bank_approval_pending' => 'amber',
        default => 'grey',
    };

    // Plan the upgrade dialog should re-open for after a validation error.
    $reopenPlan = $errors->has('phone') ? old('selected_plan') : null;
@endphp

{{-- Header --}}
<div class="d-flex align-items-center gap-3 mb-4 db-in">
    <span class="ms-head-icon" style="color: var(--wa-purple); background: var(--wa-purple-light);"><i class="bi bi-credit-card"></i></span>
    <div>
        <h1 class="h3 mb-0">Billing</h1>
        <div class="text-muted small">Manage your plan, track your usage, and upgrade anytime.</div>
    </div>
</div>

<div class="row g-4 mb-5">
    {{-- Current plan --}}
    <div class="col-lg-5">
        <div class="bl-current db-in" style="--i: 1;">
            <span class="bl-current-glow" aria-hidden="true"></span>
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <span class="bl-current-icon"><i class="bi {{ $tierIcon($subscription->plan) }}"></i></span>
                @if ($isPaid)
                    <span class="bl-status bl-status-{{ $statusTone }}">
                        <span class="in-pill-dot"></span>{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                    </span>
                @endif
            </div>

            <div class="bl-current-label">Current plan</div>
            <div class="bl-current-name">{{ $current['name'] }}</div>
            <div class="bl-current-price">
                @if ($current['price'] > 0)
                    &#8377;{{ number_format($current['price']) }}<span>/month</span>
                @else
                    Free<span> forever</span>
                @endif
            </div>

            <ul class="bl-current-list">
                <li><i class="bi bi-check2"></i>{{ $instanceLimit }} instance{{ $instanceLimit > 1 ? 's' : '' }}</li>
                <li><i class="bi bi-check2"></i>{{ number_format($messageLimit) }} messages/mo</li>
                @if ($isPaid && $subscription->current_period_end)
                    <li><i class="bi bi-calendar-check"></i>Renews {{ $subscription->current_period_end->format('M j, Y') }}</li>
                @endif
            </ul>

            <div class="bl-current-actions">
                @if ($isPaid)
                    <form method="POST" action="{{ route('billing.cancel') }}"
                          onsubmit="return confirm('Cancel your subscription? You will be moved to the Free plan immediately. Already-charged amounts for this period are not refunded.');">
                        @csrf
                        <button type="submit" class="bl-cancel">Cancel subscription</button>
                    </form>
                @else
                    <a href="#plans" class="btn btn-light fw-semibold bl-btn-lift"><i class="bi bi-rocket-takeoff me-1"></i>Upgrade your plan</a>
                @endif
            </div>
        </div>
    </div>

    {{-- Usage --}}
    <div class="col-lg-7">
        <div class="card shadow-sm h-100 db-in" style="--i: 2;">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <h2 class="h5 mb-0">Usage</h2>
                    <span class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i>Message count resets on {{ $resetsOn->format('M j') }}</span>
                </div>

                <div class="row g-4 my-auto">
                    @foreach ([
                        ['Instances', 'bi-hdd-stack', $instanceCount, $instanceLimit, $instancePct, 'WhatsApp numbers connected'],
                        ['Messages this month', 'bi-send', $messageCount, $messageLimit, $messagePct, 'Sent messages (incoming are free)'],
                    ] as [$label, $icon, $used, $limit, $pct, $hint])
                        @php $tone = $toneFor($pct); @endphp
                        <div class="col-sm-6">
                            <div class="bl-meter bl-tone-{{ $tone }}">
                                <svg class="bl-ring" viewBox="0 0 120 120" aria-hidden="true">
                                    <circle class="bl-ring-track" cx="60" cy="60" r="52" />
                                    <circle class="bl-ring-fill" cx="60" cy="60" r="52" style="--pct: {{ $pct }};" />
                                </svg>
                                <div class="bl-ring-center">
                                    <i class="bi {{ $icon }}"></i>
                                    <span class="bl-ring-pct">{{ $pct }}%</span>
                                </div>
                                <div class="bl-meter-text">
                                    <div class="fw-semibold">{{ $label }}</div>
                                    <div class="bl-meter-count">
                                        <span data-count-up="{{ $used }}">{{ $used }}</span>
                                        <span class="text-muted fw-normal">/ {{ number_format($limit) }}</span>
                                    </div>
                                    <div class="small text-muted">{{ $hint }}</div>
                                    @if ($used >= $limit)
                                        <a href="#plans" class="small fw-semibold text-danger text-decoration-none">
                                            <i class="bi bi-exclamation-circle me-1"></i>Limit reached. Upgrade for more
                                        </a>
                                    @elseif ($pct >= 90)
                                        <a href="#plans" class="small fw-semibold text-danger text-decoration-none">
                                            <i class="bi bi-exclamation-circle me-1"></i>Almost at your limit. Upgrade
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Plans --}}
<div id="plans" class="bl-plans-head db-in" style="--i: 3;">
    <h2 class="h4 mb-1">Plans</h2>
    <p class="text-muted mb-0">Upgrade or switch anytime. You'll confirm the payment on Cashfree's secure page.</p>
</div>

<div class="row g-4 align-items-stretch pt-2">
    @foreach ($plans as $key => $plan)
        @php
            $isCurrent = $subscription->plan === $key;
            $isPopular = ! empty($plan['popular']);
            $verb = $plan['price'] > $current['price'] ? 'Upgrade to' : 'Switch to';
        @endphp
        <div class="col-sm-6 col-xl-3">
            <div class="bl-plan {{ $isPopular ? 'bl-plan-popular' : '' }} {{ $isCurrent ? 'bl-plan-current' : '' }} db-in" style="--i: {{ $loop->index + 4 }};">
                @if ($isCurrent)
                    <span class="bl-plan-badge bl-plan-badge-current"><i class="bi bi-check-circle-fill"></i> Current plan</span>
                @elseif ($isPopular)
                    <span class="bl-plan-badge"><i class="bi bi-star-fill"></i> Most popular</span>
                @endif

                <span class="bl-plan-icon"><i class="bi {{ $tierIcon($key) }}"></i></span>
                <div class="bl-plan-name">{{ $plan['name'] }}</div>
                <p class="bl-plan-desc">{{ $plan['description'] }}</p>
                <div class="bl-plan-price">
                    @if ($plan['price'] > 0)
                        &#8377;{{ number_format($plan['price']) }}<span>/mo</span>
                    @else
                        Free
                    @endif
                </div>
                <ul class="bl-plan-list">
                    <li><i class="bi bi-check-lg"></i>{{ $plan['instances'] }} instance{{ $plan['instances'] > 1 ? 's' : '' }}</li>
                    <li><i class="bi bi-check-lg"></i>{{ number_format($plan['messages_per_month']) }} messages/mo</li>
                    <li><i class="bi bi-check-lg"></i>Full REST API access</li>
                    <li><i class="bi bi-check-lg"></i>Webhook delivery</li>
                </ul>

                <div class="mt-auto">
                    @if ($isCurrent)
                        <button class="btn bl-plan-btn btn-outline-secondary w-100" disabled><i class="bi bi-check2 me-1"></i>Your current plan</button>
                    @elseif ($plan['price'] > 0)
                        <button type="button" class="btn bl-plan-btn {{ $isPopular ? 'btn-primary' : 'btn-outline-primary' }} w-100"
                                data-bs-toggle="modal" data-bs-target="#upgradeModal"
                                data-plan="{{ $key }}"
                                data-plan-name="{{ $plan['name'] }}"
                                data-plan-price="{{ number_format($plan['price']) }}"
                                data-plan-instances="{{ $plan['instances'] }}"
                                data-plan-messages="{{ number_format($plan['messages_per_month']) }}"
                                data-plan-verb="{{ $verb }}"
                                data-action="{{ route('billing.subscribe', $key) }}">
                            {{ $verb }} {{ $plan['name'] }} <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    @else
                        <p class="small text-muted text-center mb-0">Cancel your subscription to return to Free.</p>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="bl-note db-in" style="--i: 8;">
    <i class="bi bi-shield-check"></i>
    <div>
        Payments are handled by <strong>Cashfree</strong> on their own secure checkout page. We never see your card or bank details.
        Cashfree is in <strong>sandbox/test mode</strong> &mdash; no real money moves.
    </div>
</div>

{{-- Upgrade dialog: one phone field for whichever plan was picked. --}}
<div class="modal fade" id="upgradeModal" tabindex="-1" aria-labelledby="upgradeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ $reopenPlan ? route('billing.subscribe', $reopenPlan) : '#' }}" class="modal-content bl-modal" data-upgrade-form>
            @csrf
            <input type="hidden" name="selected_plan" value="{{ $reopenPlan }}" data-upgrade-plan>

            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="upgradeModalTitle" data-upgrade-title>Upgrade</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="bl-modal-summary">
                    <div>
                        <div class="small text-muted">You're choosing</div>
                        <div class="fw-bold fs-5" data-upgrade-name></div>
                        <div class="small text-muted"><span data-upgrade-instances></span> instances &middot; <span data-upgrade-messages></span> messages/mo</div>
                    </div>
                    <div class="text-end">
                        <div class="bl-modal-price">&#8377;<span data-upgrade-price></span></div>
                        <div class="small text-muted">per month</div>
                    </div>
                </div>

                <label for="upgrade-phone" class="form-label small fw-semibold mt-3">Mobile number for the payment</label>
                <input type="text" id="upgrade-phone" name="phone" value="{{ old('phone') }}" inputmode="numeric" required
                       class="form-control @error('phone') is-invalid @enderror" placeholder="919876543210">
                @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Country code first, digits only (no <code>+</code> or spaces). Cashfree uses it to set up the monthly payment.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Not now</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Continue to secure checkout</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('upgradeModal');
    const form = modal.querySelector('[data-upgrade-form]');
    const plans = {};

    document.querySelectorAll('[data-plan]').forEach((button) => {
        plans[button.dataset.plan] = button;
    });

    function fill(button) {
        form.action = button.dataset.action;
        form.querySelector('[data-upgrade-plan]').value = button.dataset.plan;
        form.querySelector('[data-upgrade-title]').textContent = button.dataset.planVerb + ' ' + button.dataset.planName;
        form.querySelector('[data-upgrade-name]').textContent = button.dataset.planName;
        form.querySelector('[data-upgrade-price]').textContent = button.dataset.planPrice;
        form.querySelector('[data-upgrade-instances]').textContent = button.dataset.planInstances;
        form.querySelector('[data-upgrade-messages]').textContent = button.dataset.planMessages;
    }

    // Bootstrap tells us which button opened the dialog.
    modal.addEventListener('show.bs.modal', (event) => {
        if (event.relatedTarget) {
            fill(event.relatedTarget);
        }
    });
    modal.addEventListener('shown.bs.modal', () => document.getElementById('upgrade-phone').focus());

    // After a validation error, re-open the dialog for the same plan.
    const reopen = @json($reopenPlan);
    if (reopen && plans[reopen]) {
        window.addEventListener('load', () => plans[reopen].click());
    }
})();
</script>
@endsection
