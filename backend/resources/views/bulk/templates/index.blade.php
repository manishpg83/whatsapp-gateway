@extends('layouts.app')

@section('title', 'Saved messages · Bulk messages')

@section('content')
<a href="{{ route('bulk.index') }}" class="bk-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Bulk messages
</a>

<div class="d-flex align-items-center gap-3 mb-4 db-in" style="--i: 1;">
    <span class="ms-head-icon"><i class="bi bi-bookmark-star"></i></span>
    <div>
        <h1 class="h3 mb-0">Saved messages</h1>
        <div class="text-muted small">Messages you send often — load one into a new campaign in a click. {{ $templates->count() }} / {{ $max }} used.</div>
    </div>
</div>

<div class="row g-4">
    {{-- New saved message --}}
    <div class="col-lg-4">
        <div class="card shadow-sm db-in" style="--i: 2;">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i>New saved message</h2>
                <form method="POST" action="{{ route('bulk.templates.store') }}">
                    @csrf
                    @include('bulk.templates._fields', ['template' => null])
                    <button type="submit" class="btn btn-primary w-100 mt-3" @disabled($templates->count() >= $max)>
                        <i class="bi bi-bookmark-plus me-1"></i>Save message
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="col-lg-8">
        @if ($templates->isEmpty())
            <div class="card shadow-sm db-in" style="--i: 3;">
                <div class="card-body text-center py-5 px-4">
                    <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                        <span class="in-ring"></span>
                        <span class="in-ring in-ring-2"></span>
                        <span class="in-empty-icon"><i class="bi bi-bookmark-star"></i></span>
                    </div>
                    <h2 class="h5 mb-2">No saved messages yet.</h2>
                    <p class="text-muted mb-0 mx-auto" style="max-width: 28rem;">
                        Save greetings and offers you send often — like "Happy Diwali, {name}!" — and reuse them in any campaign.
                    </p>
                </div>
            </div>
        @else
            <div class="row g-3">
                @foreach ($templates as $template)
                    <div class="col-md-6">
                        <div class="bk-template-card db-in" style="--i: {{ min($loop->iteration + 2, 12) }};">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="fw-semibold text-break">{{ $template->name }}</div>
                                <span class="small text-muted text-nowrap">{{ $template->updated_at->format('M j') }}</span>
                            </div>
                            <div class="bk-bubble bk-bubble-text">{{ $template->body }}</div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('bulk.templates.edit', $template->id) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                                <form method="POST" action="{{ route('bulk.templates.destroy', $template->id) }}"
                                      onsubmit="return confirm('Delete this saved message?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-3" aria-label="Delete {{ $template->name }}" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
