@extends('layouts.app')

@section('title', 'Chatbot')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 db-in">
    <div class="d-flex align-items-center gap-3">
        <span class="ms-head-icon"><i class="bi bi-robot"></i></span>
        <div>
            <h1 class="h3 mb-0">Chatbot</h1>
            <div class="text-muted small">Answer customers automatically when their message has one of your keywords. Other messages are left for you.</div>
        </div>
    </div>

    @if ($instances->count() > 1)
        <form method="GET" action="{{ route('chatbot.index') }}" class="d-flex align-items-center gap-2">
            <label for="cb-instance" class="small text-muted text-nowrap mb-0">Instance</label>
            <select id="cb-instance" name="instance" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($instances as $instance)
                    <option value="{{ $instance->instance_id }}" @selected($instance->is($selected))>{{ $instance->name }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-outline-primary">Show</button></noscript>
        </form>
    @endif
</div>

@if (! $selected)
    {{-- No instance yet — the chatbot belongs to one. --}}
    <div class="card shadow-sm db-in" style="--i: 2;">
        <div class="card-body text-center py-5 px-4">
            <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                <span class="in-ring"></span>
                <span class="in-ring in-ring-2"></span>
                <span class="in-empty-icon"><i class="bi bi-robot"></i></span>
            </div>
            <h2 class="h5 mb-2">Create an instance first.</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 28rem;">
                The chatbot answers messages received on a connected WhatsApp number, so connect one to get started.
            </p>
            <a href="{{ route('instances.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New instance</a>
        </div>
    </div>
@else
    {{-- ON/OFF switch for this instance --}}
    <div class="card shadow-sm mb-4 db-in" style="--i: 2;">
        <div class="card-body d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold text-break">
                    Auto-reply for {{ $selected->name }}
                    @if ($selected->chatbot_enabled)
                        <span class="badge rounded-pill bg-wa-light text-primary border ms-1"><i class="bi bi-check-circle me-1"></i>ON</span>
                    @else
                        <span class="badge rounded-pill text-bg-light border ms-1"><i class="bi bi-pause-circle me-1"></i>OFF</span>
                    @endif
                </div>
                <div class="small text-muted">
                    @if ($selected->chatbot_enabled && $selected->status !== 'connected')
                        <i class="bi bi-exclamation-triangle text-warning me-1"></i>This instance isn't connected — replies start again once it is.
                    @elseif ($selected->chatbot_enabled)
                        Messages with one of your keywords get the answer automatically. Everything else is left for you.
                    @else
                        Switch on to start answering matching messages automatically.
                    @endif
                </div>
                @error('enabled') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <form method="POST" action="{{ route('chatbot.toggle', $selected->instance_id) }}" class="flex-shrink-0">
                @csrf
                <input type="hidden" name="enabled" value="{{ $selected->chatbot_enabled ? 0 : 1 }}">
                @if ($selected->chatbot_enabled)
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-pause-fill me-1"></i>Switch off</button>
                @else
                    <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Switch on</button>
                @endif
            </form>
        </div>
    </div>

    <div class="row g-4">
        {{-- New entry --}}
        <div class="col-lg-4">
            <div class="card shadow-sm db-in" style="--i: 2;">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-1"><i class="bi bi-plus-circle me-1 text-primary"></i>New entry</h2>
                    <div class="small text-muted mb-3 text-break">For {{ $selected->name }} · {{ $rules->count() }} / {{ $max }} used</div>
                    <form method="POST" action="{{ route('chatbot.rules.store', $selected->instance_id) }}">
                        @csrf
                        @include('chatbot._fields', ['rule' => null])
                        <button type="submit" class="btn btn-primary w-100 mt-3" @disabled($rules->count() >= $max)>
                            <i class="bi bi-plus-lg me-1"></i>Add entry
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Entries --}}
        <div class="col-lg-8">
            @if ($rules->isEmpty())
                <div class="card shadow-sm db-in" style="--i: 3;">
                    <div class="card-body text-center py-5 px-4">
                        <div class="in-empty-art mx-auto mb-4" aria-hidden="true">
                            <span class="in-ring"></span>
                            <span class="in-ring in-ring-2"></span>
                            <span class="in-empty-icon"><i class="bi bi-chat-square-dots"></i></span>
                        </div>
                        <h2 class="h5 mb-2">No entries yet.</h2>
                        <p class="text-muted mb-0 mx-auto" style="max-width: 28rem;">
                            Add the questions customers ask most — like prices, timings or your address — with the keywords that should trigger each answer.
                        </p>
                    </div>
                </div>
            @else
                {{-- Test bot: nothing is sent on WhatsApp. --}}
                @php
                    $test = session('chatbot_test');
                    $testRule = $test ? $rules->firstWhere('id', $test['rule_id']) : null;
                @endphp
                <div id="test" class="card shadow-sm mb-4 db-in" style="--i: 3;">
                    <div class="card-body">
                        <h2 class="h6 fw-semibold mb-1"><i class="bi bi-chat-dots me-1 text-primary"></i>Test bot</h2>
                        <div class="small text-muted mb-3">Type a message as if you were a customer and see what the bot would reply. Nothing is sent on WhatsApp.</div>
                        <form method="POST" action="{{ route('chatbot.test', $selected->instance_id) }}" class="d-flex flex-column flex-sm-row gap-2">
                            @csrf
                            <div class="flex-grow-1">
                                <label for="cb-test" class="visually-hidden">Test message</label>
                                <input type="text" id="cb-test" name="test_message" maxlength="4096" required
                                       value="{{ old('test_message', $test['message'] ?? '') }}" placeholder="e.g. What is the price?"
                                       class="form-control @error('test_message') is-invalid @enderror">
                                @error('test_message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-outline-primary text-nowrap align-self-start"><i class="bi bi-send me-1"></i>Test</button>
                        </form>

                        @if ($test)
                            <div class="mt-3 pt-3 border-top">
                                @if ($testRule)
                                    <div class="small text-success fw-semibold mb-2">
                                        <i class="bi bi-check-circle me-1"></i>Matched entry {{ $rules->search(fn ($r) => $r->id === $testRule->id) + 1 }}: {{ $testRule->question }}
                                    </div>
                                    @if (! empty($test['keywords']))
                                        <div class="small text-muted mb-2">
                                            Keywords found:
                                            @foreach ($test['keywords'] as $keyword)
                                                <span class="badge rounded-pill text-bg-light border">{{ $keyword }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="bk-bubble bk-bubble-text">{{ $testRule->answer }}</div>
                                @else
                                    <div class="small text-muted">
                                        <i class="bi bi-slash-circle me-1"></i>No keyword matched — the bot would stay silent and leave this message for you.
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <p class="small text-muted mb-3 db-in" style="--i: 4;">
                    <i class="bi bi-info-circle me-1"></i>If a message matches more than one entry, the one with the most matching keywords answers. On a tie, the higher one in this list wins.
                </p>
                <div class="row g-3">
                    @foreach ($rules as $rule)
                        <div class="col-md-6">
                            <div class="bk-template-card db-in" style="--i: {{ min($loop->iteration + 3, 12) }};">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold text-break">{{ $loop->iteration }}. {{ $rule->question }}</div>
                                    <span class="small text-muted text-nowrap">{{ $rule->updated_at->format('M j') }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($rule->keywords as $keyword)
                                        <span class="badge rounded-pill text-bg-light border text-break">{{ $keyword }}</span>
                                    @endforeach
                                </div>
                                <div class="bk-bubble bk-bubble-text">{{ $rule->answer }}</div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('chatbot.rules.edit', [$selected->instance_id, $rule->id]) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    <form method="POST" action="{{ route('chatbot.rules.destroy', [$selected->instance_id, $rule->id]) }}"
                                          onsubmit="return confirm('Delete this entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-3" aria-label="Delete {{ $rule->question }}" title="Delete">
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
@endif
@endsection
