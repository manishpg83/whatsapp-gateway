@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
@vite('resources/js/inbox.js')

{{-- On phones, hidden while a chat is open (see .ib-open-hide). --}}
<div class="{{ $chat !== null ? 'ib-open-hide' : '' }}">
    <div class="d-flex align-items-center gap-3 mb-4 db-in">
        <span class="ms-head-icon"><i class="bi bi-chat-square-text"></i></span>
        <div style="min-width: 0;">
            <h1 class="h3 mb-0">Inbox</h1>
            <div class="text-muted small">Your WhatsApp conversations, one per customer.</div>
        </div>
    </div>

    @include('partials.instance-switcher', [
        'route' => 'inbox.index',
        'label' => 'Each number has its own conversations — choose which one to view',
        'badge' => 'Viewing',
    ])
</div>

@if (! $selected)
    <div class="card shadow-sm db-in" style="--i: 2;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi bi-chat-square-text"></i></span>
            </div>
            <h2 class="h5 mb-2">Create an instance first.</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 28rem;">
                Conversations appear here once a WhatsApp number is connected and messages come in.
            </p>
            <a href="{{ route('instances.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New instance</a>
        </div>
    </div>
@else
    @php
        $open = $chat !== null;
    @endphp

    {{-- inbox.js polls data-ib-updates for changes; the versions say what's shown now. --}}
    <div class="ib-shell {{ $open ? 'is-open' : '' }} db-in" style="--i: 2;"
         data-ib-updates="{{ route('inbox.updates', array_filter(['instance' => $selected->instance_id, 'chat' => $chat, 'search' => $search ?: null])) }}"
         data-ib-list-version="{{ $listVersion }}" data-ib-thread-version="{{ $threadVersion }}">
        {{-- ======================================================== Conversations --}}
        <aside class="ib-list" aria-label="Conversations">
            <form method="GET" action="{{ route('inbox.index') }}" class="ib-search">
                <input type="hidden" name="instance" value="{{ $selected->instance_id }}">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="ib-search" class="visually-hidden">Search a number</label>
                <input type="search" id="ib-search" name="search" value="{{ $search }}" placeholder="Search a number" inputmode="numeric" class="form-control">
            </form>

            <div class="ib-rows" data-ib-list>
                @include('inbox._rows')
            </div>
        </aside>

        {{-- ======================================================== Open chat --}}
        <section class="ib-chat" aria-label="Conversation">
            @if (! $open)
                <div class="ib-chat-empty">
                    <span class="ib-chat-empty-icon"><i class="bi bi-chat-square-text"></i></span>
                    <div class="fw-semibold">Choose a conversation</div>
                    <div class="small text-muted">Pick a customer on the left to see your messages with them.</div>
                </div>
            @else
                <header class="ib-chat-head">
                    <a href="{{ route('inbox.index', array_filter(['instance' => $selected->instance_id, 'search' => $search ?: null])) }}" class="ib-back" aria-label="Back to conversations">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <span class="ib-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                    <div class="ib-chat-title">
                        <div class="fw-semibold text-break">{{ $chatName ?? '+'.$chat }}</div>
                        <div class="small text-muted">
                            @if ($chatName)+{{ $chat }} · @endif<span data-ib-total>{{ number_format($threadTotal) }} {{ Str::plural('message', $threadTotal) }}</span> · via {{ $selected->name }}
                        </div>
                    </div>

                    {{-- The chatbot in this chat: answering, paused for a while (a recent reply), or turned off. --}}
                    @if ($selected->chatbot_enabled && $thread->isNotEmpty())
                        <div class="ib-bot">
                            @if (! $botPause)
                                <span class="ib-bot-state is-on"><i class="bi bi-robot"></i>Bot on</span>
                            @elseif ($botPause->isTurnedOff())
                                <span class="ib-bot-state"><i class="bi bi-robot"></i>Bot off</span>
                            @else
                                <span class="ib-bot-state" title="Because you replied — answers again {{ $botPause->paused_until->diffForHumans() }}"><i class="bi bi-pause-circle"></i>Bot paused</span>
                            @endif
                            <form method="POST" action="{{ route('inbox.bot', $selected->instance_id) }}">
                                @csrf
                                <input type="hidden" name="chat" value="{{ $chat }}">
                                @if ($botPause)
                                    <button type="submit" name="bot" value="on" class="btn btn-sm btn-light border">Turn on</button>
                                @else
                                    <button type="submit" name="bot" value="off" class="btn btn-sm btn-light border"
                                            title="The chatbot won't reply to this customer until you turn it back on">Turn off</button>
                                @endif
                            </form>
                        </div>
                    @endif
                </header>

                <div class="ib-thread" data-ib-thread>
                    @include('inbox._thread')
                </div>

                <footer class="ib-chat-foot">
                    @if ($thread->isNotEmpty() && $selected->status !== 'connected')
                        <div class="ib-compose-off">
                            <i class="bi bi-wifi-off me-1"></i>{{ $selected->name }} isn't connected, so you can't reply right now.
                            <a href="{{ route('instances.show', $selected) }}">Reconnect it</a>
                        </div>
                    @elseif ($thread->isNotEmpty())
                        <form method="POST" action="{{ route('inbox.reply', $selected->instance_id) }}" enctype="multipart/form-data" data-ib-compose>
                            @csrf
                            <input type="hidden" name="chat" value="{{ $chat }}">
                            {{-- The chosen file, with a × to remove it (filled in by inbox.js). --}}
                            <div class="ib-file" data-ib-file-chip hidden>
                                <i class="bi bi-paperclip"></i><span class="text-truncate" data-ib-file-name></span>
                                <button type="button" class="btn-close" aria-label="Remove the file" data-ib-file-clear></button>
                            </div>
                            <div class="ib-compose">
                                <label class="ib-attach" title="Attach a photo, video or document">
                                    <i class="bi bi-paperclip"></i><span class="visually-hidden">Attach a file</span>
                                    <input type="file" name="file" hidden data-ib-file
                                           accept="image/jpeg,image/png,image/webp,video/mp4,video/3gpp,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">
                                </label>
                                <label for="ib-message" class="visually-hidden">Reply to +{{ $chat }}</label>
                                <textarea id="ib-message" name="message" rows="1" maxlength="4096"
                                          placeholder="Type a reply" data-ib-input
                                          class="form-control @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                                <button type="submit" class="ib-send" aria-label="Send reply" title="Send (Enter)"><i class="bi bi-send-fill"></i></button>
                            </div>
                        </form>
                        @error('message') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                        @error('file') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                        @error('chat') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    @endif
                    <div class="ib-foot-note">
                        <i class="bi bi-info-circle me-1"></i>Enter sends, Shift+Enter adds a line. With a file, your text is its caption.
                        @if ($selected->chatbot_enabled && $selected->chatbot_pause_minutes > 0)
                            Replying pauses the chatbot in this chat for {{ \App\Models\ChatbotPause::DURATIONS[$selected->chatbot_pause_minutes] ?? $selected->chatbot_pause_minutes.' minutes' }}.
                        @endif
                    </div>
                </footer>
            @endif
        </section>
    </div>
@endif
@endsection
