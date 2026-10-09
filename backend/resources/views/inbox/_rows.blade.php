{{-- The conversation list (also re-rendered by InboxController::updates).
     Needs $conversations, $hasMoreChats, $unreadChats, $listParams, $search, $unread, $chat, $chats, $selected. --}}
@php
    $chatUrl = fn (string $contact) => route('inbox.index', $listParams + ['chat' => $contact]);
    // The filter buttons start from page 1 of the list, keeping search and the open chat.
    $filterUrl = fn (bool $onlyUnread) => route('inbox.index', array_filter([
        'instance' => $selected->instance_id, 'search' => $search ?: null, 'filter' => $onlyUnread ? 'unread' : null, 'chat' => $chat,
    ]));
@endphp
<div class="ib-filter" role="group" aria-label="Show">
    <a href="{{ $filterUrl(false) }}" class="ib-filter-btn {{ $unread ? '' : 'active' }}" @if (! $unread) aria-current="true" @endif>All</a>
    <a href="{{ $filterUrl(true) }}" class="ib-filter-btn {{ $unread ? 'active' : '' }}" @if ($unread) aria-current="true" @endif>
        Unread @if ($unreadChats)<span class="ib-filter-count">{{ $unreadChats > 99 ? '99+' : $unreadChats }}</span>@endif
    </a>
</div>
@if ($conversations->isEmpty())
    <div class="ib-list-empty">
        <i class="bi {{ $unread && $search === '' ? 'bi-check2-all' : 'bi-chat-square-dots' }}"></i>
        <div class="fw-semibold">{{ $search !== '' ? 'No matching conversations.' : ($unread ? 'No unread chats.' : 'No conversations yet.') }}</div>
        <div class="small text-muted">
            @if ($search !== '' || $unread)
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
    @if ($hasMoreChats)
        <a href="{{ route('inbox.index', ['chats' => $chats + 1] + $listParams + array_filter(['chat' => $chat])) }}"
           class="ib-more" data-ib-load-chats><i class="bi bi-arrow-down me-1"></i>Load more chats</a>
    @endif
@endif
