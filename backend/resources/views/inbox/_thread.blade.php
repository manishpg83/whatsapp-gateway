{{-- The open chat's messages (also re-rendered by InboxController::updates).
     Needs $thread, $threadTotal, $hasOlder, $selected, $chat, $listParams, $older. --}}
@php
    // Failed messages that get a "Retry" button: those whose same text/file
    // hasn't gone through in a later message (e.g. an earlier retry).
    $retryable = [];
    $sentLater = [];
    foreach ($thread->reverse() as $m) {
        $key = $m->type.'|'.$m->body.'|'.$m->media_path;
        if ($m->direction === 'outgoing' && (in_array($m->status, \App\Models\Message::SENT_STATUSES, true) || $m->fallback_status === 'sent')) {
            $sentLater[$key] = true;
        } elseif (! isset($sentLater[$key]) && $m->canBeRetried()) {
            $retryable[$m->id] = true;
        }
    }
@endphp
@if ($thread->isEmpty())
    <div class="ib-note">No messages with this number yet.</div>
@else
    @if ($hasOlder)
        <a href="{{ route('inbox.index', $listParams + ['chat' => $chat, 'older' => $older + 1]) }}"
           class="ib-note ib-load" data-ib-load-older><i class="bi bi-arrow-up me-1"></i>Load earlier messages</a>
    @elseif ($threadTotal > $thread->count())
        <div class="ib-note">Showing the latest {{ number_format($thread->count()) }} messages.</div>
    @endif
    @foreach ($thread as $message)
        @if ($loop->first || ! $message->created_at->isSameDay($thread[$loop->index - 1]->created_at))
            <div class="ib-day"><span>{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('j F Y')) }}</span></div>
        @endif
        @php $out = $message->direction !== 'incoming'; @endphp
        <div class="ib-msg {{ $out ? 'ib-msg-out' : 'ib-msg-in' }} {{ $out && $message->status === 'failed' ? 'is-failed' : '' }}">
            @if ($message->type === 'text')
                <div class="ib-msg-text">{{ \App\Support\ChatbotMenu::previewHtml($message->body) }}</div>
            @else
                @include('messages._content', ['message' => $message, 'compact' => false, 'hideBot' => true])
            @endif
            <div class="ib-msg-meta">
                @if ($out)
                    @if ($message->bot_reply)
                        <span class="ib-source"><i class="bi bi-robot"></i>Bot</span>
                    @elseif ($message->api_token_id)
                        <span class="ib-source"><i class="bi bi-code-slash"></i>API</span>
                    @elseif ($message->direction === \App\Models\Message::DIRECTION_PHONE)
                        <span class="ib-source" title="Typed on your phone"><i class="bi bi-phone"></i>Phone</span>
                    @endif
                @endif
                <span>{{ $message->created_at->format('g:i A') }}</span>
                @if ($out)
                    @php [, $statusLabel, $statusIcon] = $message->statusBadge(); @endphp
                    <i class="bi {{ $message->status === 'failed' ? 'bi-exclamation-circle' : $statusIcon }} {{ $message->status === 'read' ? 'ib-tick-read' : '' }}" title="{{ $statusLabel }}"></i>
                @endif
            </div>
            @if ($out && $message->status === 'failed' && $message->error)
                <div class="ib-msg-error"><i class="bi bi-exclamation-triangle me-1"></i>Not sent: {{ Str::limit($message->error, 140) }}</div>
            @endif
            @if (isset($retryable[$message->id]))
                <form method="POST" action="{{ route('inbox.retry', [$selected->instance_id, $message->id]) }}" class="ib-retry" data-ib-retry>
                    @csrf
                    <button type="submit" class="ib-retry-btn"><i class="bi bi-arrow-clockwise me-1"></i>Retry</button>
                </form>
            @endif
        </div>
    @endforeach
@endif
