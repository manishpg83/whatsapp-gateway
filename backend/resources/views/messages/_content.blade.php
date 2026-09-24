{{-- One message's content. Expects $message; optional $compact.

     Compact (tables): every message has the same shape — a fixed-size tile
     on the left (photo thumbnail / video / file / voice icon), then a title
     line (caption, file name or type) and a small grey details line
     (type · size · download). Text messages are just their text.

     Full (the Messages page's "View" row): the real image, video or audio
     player, the file name, a download link and the full caption. --}}
@php
    $compact = $compact ?? false;
    $hasFile = $message->hasStoredMedia();
    $mediaUrl = $hasFile ? route('messages.media', $message) : null;
    $sizeLabel = $message->mediaSizeLabel();
    $isImage = in_array($message->type, ['image', 'sticker'], true) && $message->mediaIsInline();

    // For documents: the file's extension (e.g. "PDF") shown on the tile.
    $extension = $message->media_file_name ? strtoupper(pathinfo($message->media_file_name, PATHINFO_EXTENSION)) : null;

    // Colour of the tile for non-image types.
    $tileClass = match ($message->type) {
        'video' => 'media-tile-video',
        'voice', 'audio' => 'media-tile-audio',
        'document' => $extension === 'PDF' ? 'media-tile-pdf' : 'media-tile-doc',
        'location' => 'media-tile-location',
        default => 'media-tile-other',
    };

    // Title line: the caption, else the document's name, else e.g. "Photo".
    $title = $message->body !== '' ? $message->body : ($message->media_file_name ?: match ($message->type) {
        'image' => 'Photo',
        'video' => 'Video',
        'voice' => 'Voice note',
        'audio' => 'Audio',
        'sticker' => 'Sticker',
        'document' => 'Document',
        default => $message->typeLabel(),
    });
@endphp

@if ($message->type === 'text')
    <span @class(['d-inline-block text-truncate align-middle' => $compact]) style="{{ $compact ? 'max-width: 320px;' : 'white-space: pre-wrap;' }}" title="{{ $compact ? $message->body : '' }}">{{ $message->body }}</span>

@elseif ($compact)
    <div class="d-flex align-items-center gap-2" style="min-width: 0; max-width: 340px;">
        {{-- Tile --}}
        @if ($hasFile && $isImage)
            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="media-tile" title="Open full size">
                <img src="{{ $mediaUrl }}" alt="{{ $message->typeLabel() }}" loading="lazy">
            </a>
        @elseif ($hasFile)
            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="media-tile {{ $tileClass }}" title="Open">
                @if ($message->type === 'document' && $extension && strlen($extension) <= 4)
                    <span class="media-tile-ext">{{ $extension }}</span>
                @else
                    <i class="bi {{ $message->type === 'video' ? 'bi-play-fill' : $message->typeIcon() }}"></i>
                @endif
            </a>
        @else
            <span class="media-tile {{ $tileClass }}"><i class="bi {{ $message->typeIcon() }}"></i></span>
        @endif

        {{-- Title + details --}}
        <div style="min-width: 0;">
            <div class="text-truncate {{ $message->body === '' ? 'fw-medium' : '' }}" title="{{ $title }}">{{ $title }}</div>
            <div class="small text-muted text-truncate">
                @if ($message->media_status === 'too_large')
                    <i class="bi bi-exclamation-triangle text-warning"></i> File too large to download{{ $sizeLabel ? " ({$sizeLabel})" : '' }}
                @elseif ($message->media_status !== null && ! $hasFile)
                    <i class="bi bi-exclamation-triangle text-warning"></i> File not available
                @else
                    {{ $message->typeLabel() }}@if ($sizeLabel) &middot; {{ $sizeLabel }}@endif
                    @if ($hasFile)
                        &middot; <a href="{{ $mediaUrl }}?download=1" class="text-decoration-none" title="Download"><i class="bi bi-download"></i></a>
                    @endif
                @endif
            </div>
        </div>
    </div>

@else
    {{-- Full view --}}
    <div>
        @if ($hasFile && $isImage)
            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" title="Open full size">
                <img src="{{ $mediaUrl }}" alt="{{ $message->typeLabel() }}" loading="lazy" class="rounded border bg-light mb-2" style="max-width: 260px; max-height: 260px; object-fit: contain;">
            </a>
        @elseif ($hasFile && in_array($message->type, ['voice', 'audio'], true) && $message->mediaIsInline())
            <audio controls preload="none" src="{{ $mediaUrl }}" class="d-block mb-2" style="max-width: 300px;"></audio>
        @elseif ($hasFile && $message->type === 'video' && $message->mediaIsInline())
            <video controls preload="metadata" src="{{ $mediaUrl }}" class="d-block rounded border mb-2" style="max-width: 320px; max-height: 240px;"></video>
        @endif

        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            @unless ($hasFile && ($isImage || in_array($message->type, ['voice', 'audio', 'video'], true)))
                <span class="media-tile media-tile-sm {{ $tileClass }}"><i class="bi {{ $message->typeIcon() }}"></i></span>
            @endunless
            @if ($message->media_file_name)
                <span class="fw-medium text-break">{{ $message->media_file_name }}</span>
            @endif
            <span class="text-muted">
                {{ $message->typeLabel() }}@if ($sizeLabel) &middot; {{ $sizeLabel }}@endif
            </span>
            @if ($hasFile)
                <a href="{{ $mediaUrl }}?download=1" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-download me-1"></i>Download</a>
            @elseif ($message->media_status === 'too_large')
                <span class="text-muted"><i class="bi bi-exclamation-triangle text-warning me-1"></i>File too large to download</span>
            @elseif ($message->media_status !== null)
                <span class="text-muted"><i class="bi bi-exclamation-triangle text-warning me-1"></i>File not available</span>
            @endif
        </div>

        @if ($message->body !== '')
            <div class="mt-2" style="white-space: pre-wrap;">{{ $message->body }}</div>
        @endif
    </div>
@endif
