@extends('layouts.app')

@section('title', 'Bulk messages')

@section('content')
{{-- Header --}}
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4 db-in">
    <div class="d-flex align-items-center gap-3">
        <span class="ms-head-icon"><i class="bi bi-megaphone"></i></span>
        <div>
            <h1 class="h3 mb-0">Bulk messages</h1>
            <div class="text-muted small">Send one message to many numbers — delivered one by one at a steady pace.</div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="{{ route('bulk.templates.index') }}" class="btn btn-light border d-inline-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-bookmark-star"></i> Saved messages
        </a>
        <a href="{{ route('bulk.create') }}" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-plus-lg"></i> New campaign
        </a>
    </div>
</div>

@if ($campaigns->isEmpty())
    <div class="card shadow-sm db-in" style="--i: 1;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi bi-megaphone"></i></span>
            </div>
            <h2 class="h5 mb-2">No campaigns yet.</h2>
            <p class="text-muted mx-auto mb-4" style="max-width: 32rem;">
                Paste a list of numbers, write your message, and we'll send it from your connected WhatsApp
                number — one message every few seconds, so your number isn't flagged for sending in bursts.
            </p>
            <a href="{{ route('bulk.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create your first campaign</a>
        </div>
    </div>
@else
    <div class="card shadow-sm overflow-hidden db-in" style="--i: 1;">
        <div class="list-group list-group-flush">
            @foreach ($campaigns as $campaign)
                @php
                    [$label, $color, $icon] = $campaign->statusBadge();
                    $total = max(1, $campaign->recipients_count);
                    $done = $campaign->recipients_count - $campaign->pending_count;
                @endphp
                <a href="{{ route('bulk.show', $campaign) }}" class="list-group-item list-group-item-action bk-row">
                    <div class="bk-row-main">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold text-truncate">{{ $campaign->name }}</span>
                            <span class="badge rounded-pill bg-{{ $color }}-subtle text-{{ $color }}-emphasis border border-{{ $color }}-subtle">
                                <i class="bi {{ $icon }}"></i> {{ $label }}
                            </span>
                        </div>
                        <div class="small text-muted text-truncate mt-1">
                            @php [$typeLabel, $typeIcon] = \App\Models\BulkCampaign::TYPES[$campaign->type] ?? ['Text', 'bi-chat-left-text']; @endphp
                            <i class="bi {{ $typeIcon }} me-1"></i>{{ $typeLabel }}
                            <span class="mx-1">·</span><i class="bi bi-hdd-stack me-1"></i>{{ $campaign->whatsappSession->name }}
                            <span class="mx-1">·</span>{{ $campaign->created_at->format('M j, Y H:i') }}
                        </div>
                        @if ($campaign->status === 'scheduled')
                            <div class="small text-info-emphasis mt-1">
                                <i class="bi bi-calendar-event me-1"></i>Starts {{ $campaign->scheduledAtLocal()->format('M j, Y \a\t g:i A') }} ({{ $campaign->scheduledAtLocal()->format('T') }})
                            </div>
                        @endif
                    </div>
                    <div class="bk-row-progress">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>{{ number_format($done) }}</strong> / {{ number_format($campaign->recipients_count) }} done</span>
                            <span class="text-muted">
                                <span class="text-success">{{ number_format($campaign->sent_count) }} sent</span>
                                @if ($campaign->failed_count)
                                    · <span class="text-danger">{{ number_format($campaign->failed_count) }} failed</span>
                                @endif
                            </span>
                        </div>
                        <div class="progress bk-progress" role="progressbar" aria-label="Progress" aria-valuenow="{{ $done }}" aria-valuemin="0" aria-valuemax="{{ $campaign->recipients_count }}">
                            <div class="progress-bar bg-success" style="width: {{ $campaign->sent_count / $total * 100 }}%"></div>
                            <div class="progress-bar bg-danger" style="width: {{ $campaign->failed_count / $total * 100 }}%"></div>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted d-none d-md-inline" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-3">{{ $campaigns->links() }}</div>
@endif
@endsection
