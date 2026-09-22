@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Welcome, {{ $user->name }}</h1>
    <p class="text-muted mb-0">Here is an overview of your WhatsApp Gateway account.</p>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Instances</div>
                <div class="display-6 fw-semibold">{{ $instanceCount }}</div>
                <div class="text-muted small">WhatsApp connections you have created</div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
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

    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Account</div>
                <div class="fw-semibold">{{ $user->name }}</div>
                <div class="text-muted small">{{ $user->email }}</div>
                <div class="text-muted small">Member since {{ $user->created_at->format('M j, Y') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Getting started --}}
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Getting started</div>
    <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>1. Create a WhatsApp instance</span>
            <a href="{{ route('instances.create') }}" class="btn btn-sm btn-outline-primary">Create instance</a>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>2. Scan the QR code with your phone</span>
            <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary">View instances</a>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>3. Get your instance ID and access token</span>
            <span class="badge text-bg-secondary">coming soon</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>4. Send your first message through the API</span>
            <span class="badge text-bg-secondary">coming soon</span>
        </li>
    </ul>
</div>
@endsection
