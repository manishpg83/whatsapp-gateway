{{-- The conversation list (also re-rendered by InboxController::updates).
     Needs $conversations, $selected, $search, $chat. --}}
@php
    $chatUrl = fn (string $contact) => route('inbox.index', array_filter(['instance' => $selected->instance_id, 'chat' => $contact, 'search' => $search ?: null]));
@endphp
@if ($conversations->isEmpty())
    <div class="ib-list-empty">
        <i class="bi bi-chat-square-dots"></i>
        <div class="fw-semibold">{{ $search !== '' ? 'No matching conversations.' : 'No conversations yet.' }}</div>
        <div class="small text-muted">
            @if ($search !== '')
                <a href="{{ route('inbox.index', ['instance' => $selected->instance_id]) }}">Show all</a>
            @else
                Messages to and from {{ $selected->name }} show up here.
            @endif
        </div>
    </div>
@else
    <nav aria-label="Conversations">
        @foreach ($conversations as $conversation)
            @php
                $last = $conversation->last;
                $active = $conversation->contact === $chat;
                $preview = match (true) {
                    $last->type === 'text' => $last->body,
                    $last->body !== '' => $last->body,
                    default => $last->typeLabel(),
                };
            @endphp
            <a href="{{ $chatUrl($conversation->contact) }}" class="ib-row {{ $active ? 'active' : '' }} {{ $conversation->unread ? 'is-unread' : '' }}" @if ($active) aria-current="true" @endif>
                <span class="ib-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                <span class="ib-row-main">
                    <span class="ib-row-top">
                        <span class="ib-row-name" title="+{{ $conversation->contact }}">{{ $conversation->name ?? '+'.$conversation->contact }}</span>
                        <span class="ib-row-time">{{ $last->created_at->isToday() ? $last->created_at->format('g:i A') : ($last->created_at->isYesterday() ? 'Yesterday' : $last->created_at->format('M j')) }}</span>
                    </span>
                    <span class="ib-row-preview">
                        @if ($last->direction !== 'incoming')
                            <i class="bi {{ $last->status === 'failed' ? 'bi-exclamation-circle text-danger' : ($last->status === 'read' ? 'bi-check-all ib-tick-read' : ($last->status === 'delivered' ? 'bi-check-all' : 'bi-check')) }}"></i>
                            @if ($last->bot_reply)<span class="ib-row-tag">Bot:</span>@endif
                        @endif
                        @if ($last->type !== 'text')<i class="bi {{ $last->typeIcon() }}"></i>@endif
                        <span class="ib-row-text">{{ Str::limit(preg_replace('/\s+/', ' ', $preview), 80) }}</span>
                        @if ($conversation->unread)
                            <span class="ib-unread" title="{{ $conversation->unread }} unread">{{ $conversation->unread > 99 ? '99+' : $conversation->unread }}</span>
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </nav>
@endif
