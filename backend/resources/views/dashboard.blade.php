@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
{{-- Hero --}}
<div class="bg-wa-light rounded-4 p-4 p-md-5 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <div class="text-muted">Welcome,</div>
        <h1 class="h3 mb-1">{{ $user->name }}</h1>
        <p class="text-muted mb-0">Here is an overview of your WhatsApp Gateway account.</p>
    </div>

    <div class="hero-illustration position-relative d-none d-md-block">
        <div class="hero-blob hero-blob-1"></div>
        <div class="hero-blob hero-blob-2"></div>
        <div class="hero-phone mx-auto">
            <i class="bi bi-phone fs-1"></i>
            <span class="hero-phone-badge"><i class="bi bi-whatsapp"></i></span>
        </div>
    </div>

    <a href="{{ route('instances.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 flex-shrink-0">
        <i class="bi bi-plus-lg"></i> Create instance
    </a>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="bg-wa-light text-primary rounded-3 p-2 fs-4 lh-1">
                    <i class="bi bi-hdd-stack"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase">Instances</div>
                    <div class="display-6 fw-semibold">{{ $instanceCount }}</div>
                    <div class="text-muted small">WhatsApp connections you have created</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-blue shadow-sm h-100">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="rounded-3 p-2 fs-4 lh-1" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                    <i class="bi bi-wifi"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase">Connection status</div>
                    @if ($instanceCount === 0)
                        <div class="fs-4 fw-semibold"><span class="badge text-bg-secondary">No instances yet</span></div>
                        <div class="text-muted small mt-2">Create an instance to connect WhatsApp.</div>
                    @else
                        <div class="fs-4 fw-semibold">{{ $connectedCount }} of {{ $instanceCount }} connected</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--wa-primary-dark);">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="bg-wa-light text-primary rounded-3 p-2 fs-4 lh-1">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase">Messages</div>
                    <div class="text-muted small">
                        <i class="bi bi-arrow-up-short text-primary"></i>{{ $sentCount }} sent
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-arrow-down-short text-primary"></i>{{ $receivedCount }} received
                    </div>
                    <div class="text-muted small mt-1">Across all your instances</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="rounded-3 p-2 fs-4 lh-1" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
                    <i class="bi bi-person-circle"></i>
                </div>
                {{-- min-width: 0 lets long names/emails shrink and get "…" instead of spilling out of the card. --}}
                <div style="min-width: 0;">
                    <div class="text-muted small text-uppercase">Account</div>
                    <div class="fw-semibold text-truncate" title="{{ $user->name }}">{{ $user->name }}</div>
                    <div class="text-muted small text-truncate" title="{{ $user->email }}">{{ $user->email }}</div>
                    <div class="text-muted small">Member since {{ $user->created_at->format('M j, Y') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Getting started --}}
<div class="card shadow-sm">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-rocket-takeoff"></i>
        </div>
        <div>
            <div class="fw-semibold">Getting started</div>
            <div class="text-muted small">Follow these simple steps to get your WhatsApp integration up and running.</div>
        </div>
    </div>
    <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3">
            <span class="d-flex align-items-center gap-3">
                <span class="step-number step-number-1">1</span>
                <span>
                    <span class="d-block fw-semibold">Create a WhatsApp instance</span>
                    <span class="d-block text-muted small">Set up your WhatsApp connection in just a few clicks.</span>
                </span>
            </span>
            <a href="{{ route('instances.create') }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>Create instance</a>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3">
            <span class="d-flex align-items-center gap-3">
                <span class="step-number step-number-2">2</span>
                <span>
                    <span class="d-block fw-semibold">Open it, scan the QR code, then generate your API credentials</span>
                    <span class="d-block text-muted small">Link your WhatsApp account and get your API credentials.</span>
                </span>
            </span>
            <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-eye me-1"></i>View instances</a>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3">
            <span class="d-flex align-items-center gap-3">
                <span class="step-number step-number-3">3</span>
                <span>
                    <span class="d-block fw-semibold">Send your first message through the API</span>
                    <span class="d-block text-muted small">Start sending and receiving messages using your API credentials.</span>
                </span>
            </span>
            <a href="{{ route('docs.index') }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-code-slash me-1"></i>View API Docs</a>
        </li>
    </ul>
</div>
@endsection
