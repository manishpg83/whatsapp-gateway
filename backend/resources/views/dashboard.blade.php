@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $connectedPct = $instanceCount > 0 ? round($connectedCount / $instanceCount * 100) : 0;
@endphp

{{-- Hero --}}
<div class="db-hero db-in bg-wa-light rounded-4 p-4 p-md-5 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <span class="db-orb db-orb-1" aria-hidden="true"></span>
    <span class="db-orb db-orb-2" aria-hidden="true"></span>

    <div>
        <div class="text-muted">Welcome,</div>
        <h1 class="h3 mb-1">{{ $user->name }}</h1>
        <p class="text-muted mb-0">Here is an overview of your WhatsApp Gateway account.</p>
    </div>

    {{-- Little animated phone scene (decorative). --}}
    <div class="db-hero-art d-none d-md-block" aria-hidden="true">
        <div class="db-phone">
            <span class="db-bubble in" style="--d: 400ms;"></span>
            <span class="db-bubble out" style="--d: 900ms;"></span>
            <span class="db-bubble in short" style="--d: 1400ms;"></span>
            <span class="db-typing" style="--d: 1900ms;"><span></span><span></span><span></span></span>
        </div>
        
        <span class="db-chip"><i class="bi bi-check2-all"></i> Delivered</span>
        <span class="db-badge-wa"><i class="bi bi-whatsapp"></i></span>
    </div>

    <a href="{{ route('instances.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 flex-shrink-0">
        <i class="bi bi-plus-lg"></i> Create instance
    </a>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-green shadow-sm h-100 db-stat db-in" style="--i: 1;">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="bg-wa-light text-primary rounded-3 p-2 fs-4 lh-1 db-stat-icon">
                    <i class="bi bi-hdd-stack"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase">Instances</div>
                    <div class="display-6 fw-semibold" data-count-up="{{ $instanceCount }}">{{ $instanceCount }}</div>
                    <div class="text-muted small">WhatsApp connections you have created</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-blue shadow-sm h-100 db-stat db-in" style="--i: 2;">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="rounded-3 p-2 fs-4 lh-1 db-stat-icon" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                    <i class="bi bi-wifi"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-muted small text-uppercase">Connection status</div>
                    @if ($instanceCount === 0)
                        <div class="fs-4 fw-semibold"><span class="badge text-bg-secondary">No instances yet</span></div>
                        <div class="text-muted small mt-2">Create an instance to connect WhatsApp.</div>
                    @else
                        <div class="fs-4 fw-semibold">
                            <span class="db-live {{ $connectedCount > 0 ? 'is-on' : '' }}" aria-hidden="true"></span><span data-count-up="{{ $connectedCount }}">{{ $connectedCount }}</span> of {{ $instanceCount }} connected
                        </div>
                        <div class="db-progress" role="progressbar" aria-label="Connected instances"
                             aria-valuenow="{{ $connectedPct }}" aria-valuemin="0" aria-valuemax="100">
                            <span style="--pct: {{ $connectedPct }}%;"></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card stat-card shadow-sm h-100 db-stat db-in" style="--i: 3; border-left-color: var(--wa-primary-dark);">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="bg-wa-light text-primary rounded-3 p-2 fs-4 lh-1 db-stat-icon">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase">Messages</div>
                    <div class="text-muted small">
                        <i class="bi bi-arrow-up-short text-primary"></i><span data-count-up="{{ $sentCount }}" data-count-suffix=" sent">{{ $sentCount }} sent</span>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-arrow-down-short text-primary"></i><span data-count-up="{{ $receivedCount }}" data-count-suffix=" received">{{ $receivedCount }} received</span>
                    </div>
                    <div class="text-muted small mt-1">Across all your instances</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card stat-card stat-card-purple shadow-sm h-100 db-stat db-in" style="--i: 4;">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="rounded-3 p-2 fs-4 lh-1 db-stat-icon" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
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
<div class="card shadow-sm db-in" style="--i: 5;">
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
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3 db-step db-in" style="--i: 6;">
            <span class="d-flex align-items-center gap-3">
                <span class="step-number step-number-1">1</span>
                <span>
                    <span class="d-block fw-semibold">Create a WhatsApp instance</span>
                    <span class="d-block text-muted small">Set up your WhatsApp connection in just a few clicks.</span>
                </span>
            </span>
            <a href="{{ route('instances.create') }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>Create instance</a>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3 db-step db-in" style="--i: 7;">
            <span class="d-flex align-items-center gap-3">
                <span class="step-number step-number-2">2</span>
                <span>
                    <span class="d-block fw-semibold">Open it, scan the QR code, then generate your API credentials</span>
                    <span class="d-block text-muted small">Link your WhatsApp account and get your API credentials.</span>
                </span>
            </span>
            <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-eye me-1"></i>View instances</a>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3 db-step db-in" style="--i: 8;">
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
