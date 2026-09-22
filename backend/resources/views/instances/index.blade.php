@extends('layouts.app')

@section('title', 'Instances')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Instances</h1>
    <a href="{{ route('instances.create') }}" class="btn btn-primary">New instance</a>
</div>

@if ($instances->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <p class="mb-3">No instances yet.</p>
            <a href="{{ route('instances.create') }}" class="btn btn-primary">Create your first instance</a>
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
                        <td>{{ $instance->name }}</td>
                        <td>
                            <span class="badge text-bg-{{ match ($instance->status) {
                                'connected' => 'success',
                                'qr_pending', 'connecting' => 'warning',
                                default => 'secondary',
                            } }}">{{ str($instance->status)->replace('_', ' ') }}</span>
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
