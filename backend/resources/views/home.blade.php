@extends('layouts.app')

@section('title', 'Home')

@section('content')
{{-- Hero --}}
<div class="bg-wa-light rounded-4 p-4 p-md-5 mb-5 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
    <div>
        <h1 class="display-6 fw-bold mb-3">WhatsApp messaging for your product, without the Business API paperwork</h1>
        <p class="text-muted fs-5 mb-4" style="max-width: 40rem;">
            Connect your own WhatsApp number, scan a QR code, and start sending &amp; receiving
            messages through a simple REST API &mdash; live in minutes, not weeks of approval.
        </p>
        <div class="d-flex gap-2">
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2">
                <i class="bi bi-rocket-takeoff"></i> Create your free account
            </a>
            <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-lg">Log in</a>
        </div>
    </div>

    <div class="hero-illustration position-relative d-none d-md-block flex-shrink-0">
        <div class="hero-blob hero-blob-1"></div>
        <div class="hero-blob hero-blob-2"></div>
        <div class="hero-phone mx-auto">
            <i class="bi bi-phone fs-1"></i>
            <span class="hero-phone-badge"><i class="bi bi-whatsapp"></i></span>
        </div>
    </div>
</div>

{{-- How it works --}}
<div class="card shadow-sm mb-5">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-signpost-2"></i>
        </div>
        <div>
            <div class="fw-semibold">How it works</div>
            <div class="text-muted small">Three steps between signing up and sending your first message.</div>
        </div>
    </div>
    <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex align-items-center gap-3 py-3">
            <span class="step-number step-number-1">1</span>
            <span>
                <span class="d-block fw-semibold">Create an instance and scan a QR code</span>
                <span class="d-block text-muted small">Link your own WhatsApp number with the app you already use on your phone.</span>
            </span>
        </li>
        <li class="list-group-item d-flex align-items-center gap-3 py-3">
            <span class="step-number step-number-2">2</span>
            <span>
                <span class="d-block fw-semibold">Generate an API token</span>
                <span class="d-block text-muted small">A cryptographically random token, shown once, scoped to that instance only.</span>
            </span>
        </li>
        <li class="list-group-item d-flex align-items-center gap-3 py-3">
            <span class="step-number step-number-3">3</span>
            <span>
                <span class="d-block fw-semibold">Send &amp; receive messages through the REST API</span>
                <span class="d-block text-muted small">Call one endpoint to send; configure a webhook to receive replies.</span>
            </span>
        </li>
    </ul>
</div>

{{-- Features --}}
<div class="row g-3 mb-5">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100">
            <div class="card-body">
                <div class="bg-wa-light text-primary rounded-3 p-2 fs-4 lh-1 d-inline-flex mb-2">
                    <i class="bi bi-code-slash"></i>
                </div>
                <div class="fw-semibold">Simple REST API</div>
                <div class="text-muted small">One endpoint to send a message, authenticated with a bearer token.</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-blue shadow-sm h-100">
            <div class="card-body">
                <div class="rounded-3 p-2 fs-4 lh-1 d-inline-flex mb-2" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                    <i class="bi bi-link-45deg"></i>
                </div>
                <div class="fw-semibold">Incoming webhooks</div>
                <div class="text-muted small">Get replies delivered to your own endpoint, signed so you can verify them.</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body">
                <div class="rounded-3 p-2 fs-4 lh-1 d-inline-flex mb-2" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
                    <i class="bi bi-hdd-stack"></i>
                </div>
                <div class="fw-semibold">Multiple instances</div>
                <div class="text-muted small">Connect more than one WhatsApp number, each fully independent.</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--wa-primary-dark);">
            <div class="card-body">
                <div class="bg-wa-light text-primary rounded-3 p-2 fs-4 lh-1 d-inline-flex mb-2">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="fw-semibold">A real dashboard</div>
                <div class="text-muted small">Manage instances, tokens, and usage without touching a terminal.</div>
            </div>
        </div>
    </div>
</div>

{{-- Pricing --}}
<div class="mb-3">
    <h2 class="h4 mb-1">Simple, transparent pricing</h2>
    <p class="text-muted mb-0">Start free, upgrade whenever you outgrow it.</p>
</div>
<div class="row g-3 align-items-stretch mb-5">
    @foreach ($plans as $key => $plan)
        @php
            $isPopular = ! empty($plan['popular']);
            $tierIcon = match ($key) {
                'free' => 'bi-gift',
                'starter' => 'bi-lightning-charge',
                'growth' => 'bi-graph-up-arrow',
                default => 'bi-building',
            };
        @endphp
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm h-100 position-relative {{ $isPopular ? 'border-primary border-2' : '' }}">
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
                        <a href="{{ route('register') }}" class="btn btn-outline-primary btn-sm w-100">Get started</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Unofficial-integration disclosure --}}
<div class="alert alert-secondary d-flex align-items-start gap-2 mb-5">
    <i class="bi bi-info-circle mt-1"></i>
    <div class="small">
        This is an unofficial WhatsApp Web-style integration, not affiliated with or endorsed by
        WhatsApp/Meta &mdash; not the official WhatsApp Business Cloud API. See our
        <a href="{{ route('terms') }}">Terms of Service</a> for details.
    </div>
</div>

{{-- Final CTA --}}
<div class="bg-wa-light rounded-4 p-4 p-md-5 text-center mb-3">
    <h2 class="h4 mb-2">Ready to connect your WhatsApp number?</h2>
    <p class="text-muted mb-4">No credit card required to get started on the free plan.</p>
    <a href="{{ route('register') }}" class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2">
        <i class="bi bi-rocket-takeoff"></i> Create your free account
    </a>
</div>
@endsection
