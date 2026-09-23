@extends('layouts.app')

@section('title', 'Instances')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Instances</h1>
        <p class="text-muted mb-0">Your WhatsApp connections.</p>
    </div>
    <a href="{{ route('instances.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 flex-shrink-0">
        <i class="bi bi-plus-lg"></i> New instance
    </a>
</div>

@if ($instances->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="bg-wa-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-3" style="width: 64px; height: 64px;">
                <i class="bi bi-hdd-stack"></i>
            </div>
            <p class="mb-3 text-muted">No instances yet.</p>
            <a href="{{ route('instances.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create your first instance</a>
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Phone number</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($instances as $instance)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="bg-wa-light text-primary rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                                    <i class="bi bi-hdd-stack"></i>
                                </span>
                                {{ $instance->name }}
                            </div>
                        </td>
                        <td>
                            @php
                                $statusColor = match ($instance->status) {
                                    'connected' => 'success',
                                    'qr_pending', 'connecting' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge rounded-pill text-bg-{{ $statusColor }}">
                                <i class="bi bi-circle-fill me-1" style="font-size: .5rem;"></i>{{ str($instance->status)->replace('_', ' ') }}
                            </span>
                        </td>
                        <td>{{ $instance->phone_number ?? '—' }}</td>
                        <td>{{ $instance->created_at->format('M j, Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('instances.show', $instance) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
