@extends('layouts.app')

@section('title', $campaign->name . ' · Bulk messages')

@section('content')
@php
    [$label, $color, $icon] = $campaign->statusBadge();
    $total = max(1, $counts['total']);
    $recipientBadges = [
        'pending' => ['Waiting', 'secondary', 'bi-hourglass'],
        'sent' => ['Sent', 'success', 'bi-check'],
        'failed' => ['Failed', 'danger', 'bi-x-circle'],
        'skipped' => ['Skipped', 'secondary', 'bi-skip-forward'],
    ];
@endphp

@if ($campaign->status === 'running')
    @vite('resources/js/bulk.js')
@endif

<a href="{{ route('bulk.index') }}" class="bk-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Bulk messages
</a>

{{-- Header --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 db-in" style="--i: 1;"
     @if ($campaign->status === 'running') data-bulk-live="{{ route('bulk.status', $campaign) }}" data-bulk-status="running" data-bulk-interval="{{ $campaign->interval_seconds }}" @endif>
    <div class="d-flex align-items-center gap-3" style="min-width: 0;">
        <span class="ms-head-icon"><i class="bi bi-megaphone"></i></span>
        <div style="min-width: 0;">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h3 mb-0 text-break">{{ $campaign->name }}</h1>
                <span class="badge rounded-pill bg-{{ $color }}-subtle text-{{ $color }}-emphasis border border-{{ $color }}-subtle">
                    <i class="bi {{ $icon }}"></i> {{ $label }}
                </span>
            </div>
            <div class="text-muted small mt-1">
                From <a href="{{ route('instances.show', $campaign->whatsappSession) }}">{{ $campaign->whatsappSession->name }}</a>
                · one message every {{ $campaign->interval_seconds }} seconds
                · created {{ $campaign->created_at->format('M j, Y H:i') }}
            </div>
        </div>
    </div>

    @if ($campaign->isActive())
        <div class="d-flex flex-wrap gap-2 flex-shrink-0">
            @if ($campaign->status === 'running')
                <form method="POST" action="{{ route('bulk.pause', $campaign) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning"><i class="bi bi-pause-fill me-1"></i>Pause</button>
                </form>
            @elseif ($campaign->status === 'scheduled')
                <form method="POST" action="{{ route('bulk.resume', $campaign) }}"
                      onsubmit="return confirm('Start sending now instead of at the scheduled time?');">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send now</button>
                </form>
            @else
                <form method="POST" action="{{ route('bulk.resume', $campaign) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Resume</button>
                </form>
            @endif
            <form method="POST" action="{{ route('bulk.cancel', $campaign) }}"
                  onsubmit="return confirm('Cancel this campaign? Numbers not sent yet will be skipped. This cannot be undone.');">
                @csrf
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Cancel</button>
            </form>
        </div>
    @endif
</div>

@if ($campaign->status === 'scheduled')
    @php $local = $campaign->scheduledAtLocal(); @endphp
    <div class="alert alert-info d-flex gap-2 db-in" style="--i: 2;">
        <i class="bi bi-calendar-event-fill"></i>
        <div>
            <strong>Scheduled for {{ $local->format('l, M j, Y \a\t g:i A') }}</strong> ({{ $local->format('T') }}, {{ $local->diffForHumans() }}).
            Keep the instance connected — if it isn't at that time, the campaign pauses and you can resume it later.
        </div>
    </div>
@endif

@if ($campaign->status === 'paused' && $campaign->pause_reason)
    <div class="alert alert-warning d-flex gap-2 db-in" style="--i: 2;">
        <i class="bi bi-pause-circle-fill"></i>
        <div><strong>Paused automatically.</strong> {{ $campaign->pause_reason }}</div>
    </div>
@endif

{{-- Progress --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Total', 'total', 'bi-people', 'blue'],
        ['Sent', 'sent', 'bi-check2-circle', 'green'],
        ['Failed', 'failed', 'bi-exclamation-triangle', 'red'],
        ['Waiting', 'pending', 'bi-hourglass-split', 'purple'],
    ] as $i => [$tileLabel, $key, $tileIcon, $tone])
        <div class="col-6 col-xl-3">
            <div class="ms-tile ms-tone-{{ $tone }} db-in" style="--i: {{ $i + 2 }};">
                <span class="ms-tile-icon"><i class="bi {{ $tileIcon }}"></i></span>
                <span class="d-block" style="min-width: 0;">
                    <span class="ms-tile-label">{{ $tileLabel }}</span>
                    <span class="ms-tile-value" data-bulk-count-of="{{ $key }}">{{ number_format($counts[$key]) }}</span>
                </span>
            </div>
        </div>
    @endforeach
</div>

<div class="card shadow-sm mb-4 db-in" style="--i: 6;">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 small mb-2">
            <span><strong data-bulk-percent>{{ floor($counts['done'] / $total * 100) }}%</strong> done</span>
            <span class="text-muted" data-bulk-eta>
                @if ($campaign->status === 'running' && $counts['pending'] > 0)
                    @php $minutesLeft = (int) ceil($counts['pending'] * $campaign->interval_seconds / 60); @endphp
                    {{ $minutesLeft <= 1 ? 'Less than a minute left' : "About {$minutesLeft} min left" }}
                @elseif ($campaign->finished_at)
                    Finished {{ $campaign->finished_at->format('M j, Y H:i') }}
                @endif
            </span>
        </div>
        <div class="progress bk-progress bk-progress-lg" role="progressbar" aria-label="Progress">
            <div class="progress-bar bg-success" data-bulk-bar="sent" style="width: {{ $counts['sent'] / $total * 100 }}%"></div>
            <div class="progress-bar bg-danger" data-bulk-bar="failed" style="width: {{ $counts['failed'] / $total * 100 }}%"></div>
            <div class="progress-bar bg-secondary bg-opacity-50" data-bulk-bar="skipped" style="width: {{ $counts['skipped'] / $total * 100 }}%"></div>
        </div>
        @if ($campaign->status === 'running')
            <div class="small text-muted mt-2"><span class="bk-live-dot"></span>Updating live — you can close this page, sending continues.</div>
        @endif
    </div>
</div>

<div class="row g-4">
    {{-- Message --}}
    <div class="col-lg-4 order-lg-2">
        <div class="card shadow-sm db-in" style="--i: 7;">
            <div class="card-body">
                @php [$typeLabel, $typeIcon] = \App\Models\BulkCampaign::TYPES[$campaign->type] ?? ['Text', 'bi-chat-left-text']; @endphp
                <h2 class="h6 fw-semibold mb-3 d-flex justify-content-between align-items-center">
                    Message
                    <span class="badge rounded-pill bg-light text-body border fw-semibold"><i class="bi {{ $typeIcon }} me-1"></i>{{ $typeLabel }}</span>
                </h2>
                <div class="bk-bubble">
                    @if ($campaign->type === 'image')
                        <a href="{{ route('bulk.media', $campaign) }}" target="_blank" class="d-block">
                            <img src="{{ route('bulk.media', $campaign) }}" alt="Image sent in this campaign" class="bk-bubble-media" loading="lazy">
                        </a>
                    @elseif ($campaign->type === 'video')
                        <video src="{{ route('bulk.media', $campaign) }}" controls preload="metadata" class="bk-bubble-media"></video>
                    @elseif ($campaign->type === 'document')
                        <a href="{{ route('bulk.media', $campaign) }}" class="bk-bubble-doc">
                            <i class="bi bi-file-earmark-text"></i>
                            <span class="text-break">{{ $campaign->media_file_name ?: 'Document' }}
                                <small class="d-block text-muted">{{ number_format($campaign->media_size / 1048576, 1) }} MB · Download</small>
                            </span>
                        </a>
                    @endif
                    @if ($campaign->body !== '')
                        <div @class(['bk-bubble-text', 'mt-2' => $campaign->type !== 'text'])>{{ $campaign->body }}</div>
                    @elseif ($campaign->type !== 'text')
                        <div class="small text-muted mt-2">No caption</div>
                    @endif
                </div>
                @if (str_contains((string) $campaign->body, '{name}'))
                    <div class="small text-muted mt-2">
                        <i class="bi bi-person me-1"></i><code>{name}</code> is replaced with each person's name{{ $campaign->name_fallback ? ', or "'.$campaign->name_fallback.'" when there is none' : '' }}.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Recipients --}}
    <div class="col-lg-8 order-lg-1">
        <div class="card shadow-sm overflow-hidden db-in" style="--i: 8;">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <h2 class="h6 fw-semibold mb-0">Numbers</h2>
                <span class="small text-muted">{{ number_format($counts['total']) }} total</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 bk-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            @if ($hasNames)
                                <th class="d-none d-md-table-cell">Name</th>
                            @endif
                            <th>Status</th>
                            <th class="d-none d-sm-table-cell">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recipients as $recipient)
                            <tr>
                                <td>
                                    <span class="font-monospace">{{ $recipient->phone }}</span>
                                    @if ($recipient->name)
                                        <div class="small text-muted d-md-none">{{ $recipient->name }}</div>
                                    @endif
                                </td>
                                @if ($hasNames)
                                    <td class="d-none d-md-table-cell">{{ $recipient->name ?? '—' }}</td>
                                @endif
                                <td>
                                    @if ($recipient->message && $recipient->status === 'sent')
                                        <x-message-status :message="$recipient->message" />
                                    @else
                                        @php [$rLabel, $rColor, $rIcon] = $recipientBadges[$recipient->status] ?? $recipientBadges['failed']; @endphp
                                        <span class="badge rounded-pill bg-{{ $rColor }}-subtle text-{{ $rColor }}-emphasis border border-{{ $rColor }}-subtle">
                                            <i class="bi {{ $rIcon }}"></i> {{ $rLabel }}
                                        </span>
                                    @endif
                                    @if ($recipient->error)
                                        <div class="small text-danger mt-1 text-break">{{ $recipient->error }}</div>
                                    @endif
                                </td>
                                <td class="small text-muted d-none d-sm-table-cell text-nowrap">
                                    {{ $recipient->processed_at?->format('M j, H:i:s') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $recipients->links() }}</div>
    </div>
</div>
@endsection
