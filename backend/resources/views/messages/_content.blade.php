{{-- One message's content: text, or the media (image thumbnail, audio/video
     player, document link) plus its caption. Expects $message; optional
     $compact (true in narrow tables: small thumbnail, one-line text). --}}
@php
    $compact = $compact ?? false;
    $hasFile = $message->hasStoredMedia();
    $mediaUrl = $hasFile ? route('messages.media', $message) : null;
    $sizeLabel = $message->mediaSizeLabel();
@endphp

@if ($message->type !== 'text')
    <div class="d-flex align-items-start gap-2">
        @if ($hasFile && in_array($message->type, ['image', 'sticker'], true) && $message->mediaIsInline())
            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="flex-shrink-0" title="Open full size">
                <img src="{{ $mediaUrl }}" alt="{{ $message->typeLabel() }}" loading="lazy"
                     class="rounded border bg-light" style="object-fit: cover; {{ $compact ? 'width: 48px; height: 48px;' : 'max-width: 240px; max-height: 240px;' }}">
            </a>
        @elseif ($hasFile && in_array($message->type, ['voice', 'audio'], true) && ! $compact && $message->mediaIsInline())
            <audio controls preload="none" src="{{ $mediaUrl }}" style="max-width: 260px;"></audio>
        @elseif ($hasFile && $message->type === 'video' && ! $compact && $message->mediaIsInline())
            <video controls preload="metadata" src="{{ $mediaUrl }}" class="rounded border" style="max-width: 280px; max-height: 220px;"></video>
        @else
            <span class="badge text-bg-light border text-wrap text-start">
                <i class="bi {{ $message->typeIcon() }} me-1"></i>{{ $message->typeLabel() }}
            </span>
        @endif

        <div class="small {{ $compact ? 'text-truncate' : '' }}" style="{{ $compact ? 'max-width: 220px;' : '' }}">
            @if ($message->media_file_name)
                <div class="text-truncate" style="max-width: 260px;" title="{{ $message->media_file_name }}">{{ $message->media_file_name }}</div>
            @endif

            @if ($hasFile)
                <a href="{{ $mediaUrl }}?download=1" class="text-decoration-none"><i class="bi bi-download me-1"></i>Download</a>
                @if ($sizeLabel)<span class="text-muted">&middot; {{ $sizeLabel }}</span>@endif
            @elseif ($message->media_status === 'too_large')
                <span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>File too large to download{{ $sizeLabel ? " ({$sizeLabel})" : '' }}</span>
            @elseif ($message->media_status !== null)
                <span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>File not available</span>
            @endif

            @if ($message->body !== '')
                <div class="{{ $compact ? 'text-truncate' : '' }}" style="white-space: {{ $compact ? 'nowrap' : 'pre-wrap' }};">{{ $message->body }}</div>
            @endif
        </div>
    </div>
@else
    <span @class(['d-inline-block text-truncate' => $compact]) style="{{ $compact ? 'max-width: 280px;' : 'white-space: pre-wrap;' }}">{{ $message->body }}</span>
@endif
