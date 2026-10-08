@extends('layouts.app')

@section('title', 'Chatbot')

@section('content')
@vite('resources/js/chatbot.js')

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 db-in">
    <div class="d-flex align-items-center gap-3" style="min-width: 0;">
        <span class="ms-head-icon"><i class="bi bi-robot"></i></span>
        <div style="min-width: 0;">
            <h1 class="h3 mb-0">Chatbot</h1>
            <div class="text-muted small">
                Answer customers automatically when their message has one of your keywords.
                <a href="{{ route('guides.show', 'whatsapp-auto-reply-chatbot') }}" target="_blank" rel="noopener" class="text-nowrap">
                    <i class="bi bi-question-circle me-1"></i>How it works
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Which instance's chatbot this is. Each instance has its own entries
     and settings, so with several the choice is shown big, not hidden. --}}
@include('partials.instance-switcher', [
    'route' => 'chatbot.index',
    'label' => 'Each number has its own chatbot — choose which one to edit',
    'badge' => 'Editing',
    'meta' => fn ($instance) => $instance->chatbot_rules_count.' '.Str::plural('entry', $instance->chatbot_rules_count),
])

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
    @php
        $hours = $selected->chatbotHours();
        $menu = $selected->chatbotMenu();
        $menuOptions = \App\Support\ChatbotMenu::options($rules);
        $test = session('chatbot_test');
        $testRule = $test ? $rules->firstWhere('id', $test['rule_id']) : null;

        $hoursErrors = $errors->hasAny(['days', 'days.*', 'open', 'close', 'timezone', 'message']);
        $menuErrors = $errors->hasAny(['menu_enabled', 'intro', 'menu_keywords', 'human_reply']);
        $entryErrors = $errors->hasAny(['question', 'keywords', 'answer', 'media', 'in_menu']);

        // The tab shown first. A link with #hours, #test, #rule-5 … (every
        // save redirects to one) switches to the tab holding it — chatbot.js.
        $tab = match (true) {
            $hoursErrors || $menuErrors || $errors->has('pause_minutes') => 'settings',
            $test !== null || $errors->has('test_message') => 'test',
            default => 'entries',
        };
        $newEntryOpen = $entryErrors || $prefillQuestion !== '' || $rules->isEmpty();
        $canAdd = $rules->count() < $max && $planUsed < $planLimit;
        $pauseLabel = $selected->chatbot_pause_minutes > 0
            ? (\App\Models\ChatbotPause::DURATIONS[$selected->chatbot_pause_minutes] ?? $selected->chatbot_pause_minutes.' minutes')
            : null;
    @endphp

    {{-- Status + switch + stats --}}
    <div class="cb-hero {{ $selected->chatbot_enabled ? 'is-on' : '' }} mb-4 db-in" style="--i: 1;">
        <div class="cb-hero-top">
            <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                <span class="cb-status-dot" aria-hidden="true"><i class="bi {{ $selected->chatbot_enabled ? 'bi-robot' : 'bi-pause-fill' }}"></i></span>
                <div style="min-width: 0;">
                    <div class="fw-semibold text-break">
                        Auto-reply for {{ $selected->name }}
                        @if ($selected->chatbot_enabled)
                            <span class="cb-pill cb-pill-on ms-1"><i class="bi bi-check-circle"></i>ON</span>
                        @else
                            <span class="cb-pill ms-1"><i class="bi bi-pause-circle"></i>OFF</span>
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

        <div class="cb-stats">
            @foreach ([
                ['Answers sent · 7 days', $stats['answers_week'], 'bi-chat-left-text', 'green'],
                ['"Closed" messages · 7 days', $stats['closed_week'], 'bi-moon-stars', 'purple'],
                ['All bot replies · 30 days', $stats['month'], 'bi-graph-up', 'blue'],
            ] as [$label, $count, $icon, $tone])
                <div class="cb-stat ms-tone-{{ $tone }}">
                    <span class="cb-stat-icon"><i class="bi {{ $icon }}"></i></span>
                    <span class="d-block" style="min-width: 0;">
                        <span class="cb-stat-label">{{ $label }}</span>
                        <span class="cb-stat-value">{{ number_format($count) }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Tabs --}}
    <div class="nav cb-tabs mb-3 db-in" style="--i: 2;" role="tablist">
        @foreach ([
            'entries' => ['Entries', 'bi-collection', $rules->count()],
            'test' => ['Test bot', 'bi-chat-dots', null],
            'settings' => ['Settings', 'bi-sliders', null],
            'unanswered' => ['Unanswered', 'bi-question-circle', $unanswered->count()],
        ] as $pane => [$label, $icon, $count])
            <button type="button" class="nav-link cb-tab {{ $tab === $pane ? 'active' : '' }}" id="{{ $pane }}-tab"
                    data-bs-toggle="tab" data-bs-target="#{{ $pane }}" role="tab" aria-controls="{{ $pane }}"
                    aria-selected="{{ $tab === $pane ? 'true' : 'false' }}">
                <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
                @if ($count)
                    <span class="cb-tab-count">{{ $count }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="tab-content db-in" style="--i: 3;">
        {{-- ================================================================ Entries --}}
        <div class="tab-pane fade {{ $tab === 'entries' ? 'show active' : '' }}" id="entries" role="tabpanel" aria-labelledby="entries-tab" tabindex="0">
            <div class="cb-toolbar mb-3">
                <div class="cb-usage">
                    <div class="small text-muted text-break">
                        For {{ $selected->name }} · {{ number_format($planUsed) }} / {{ number_format($planLimit) }} entries used on your plan
                    </div>
                    @if ($planLimit > 0)
                        <div class="progress cb-usage-bar" role="progressbar" aria-label="Plan entries used"
                             aria-valuenow="{{ $planUsed }}" aria-valuemin="0" aria-valuemax="{{ $planLimit }}">
                            <div class="progress-bar {{ $planUsed >= $planLimit ? 'bg-warning' : '' }}" style="width: {{ min(100, round($planUsed / $planLimit * 100)) }}%"></div>
                        </div>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="collapse" data-bs-target="#csv"
                            aria-expanded="{{ $errors->has('csv') ? 'true' : 'false' }}" aria-controls="csv">
                        <i class="bi bi-arrow-down-up me-1"></i>Import / export
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#new-entry"
                            aria-expanded="{{ $newEntryOpen ? 'true' : 'false' }}" aria-controls="new-entry">
                        <i class="bi bi-plus-lg me-1"></i>New entry
                    </button>
                </div>
            </div>

            @if ($planLimit === 0)
                <div class="alert alert-warning small py-2">
                    The chatbot isn't included in your plan. <a href="{{ route('billing.index') }}" class="alert-link">Upgrade</a> to use it.
                </div>
            @elseif ($planUsed >= $planLimit)
                <div class="alert alert-warning small py-2">
                    You've used all your plan's chatbot entries. <a href="{{ route('billing.index') }}" class="alert-link">Upgrade</a> to add more.
                </div>
            @endif

            {{-- New entry --}}
            <div id="new-entry" class="collapse {{ $newEntryOpen ? 'show' : '' }}">
                <div class="cb-panel mb-3">
                    <div class="cb-panel-head">
                        <span class="cb-panel-icon"><i class="bi bi-plus-circle"></i></span>
                        <div>
                            <h2 class="h6 fw-semibold mb-0">New entry</h2>
                            <div class="small text-muted">A question customers ask, the keywords that spot it, and the answer to send.</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('chatbot.rules.store', $selected->instance_id) }}" enctype="multipart/form-data" class="cb-panel-body">
                        @csrf
                        <div class="cb-form-narrow">
                            @include('chatbot._fields', ['rule' => null, 'prefill' => $prefillQuestion])
                            <button type="submit" class="btn btn-primary mt-3" @disabled(! $canAdd)>
                                <i class="bi bi-plus-lg me-1"></i>Add entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Import / export (CSV) --}}
            <div id="csv" class="collapse {{ $errors->has('csv') ? 'show' : '' }}">
                <div class="cb-panel mb-3">
                    <div class="cb-panel-head">
                        <span class="cb-panel-icon"><i class="bi bi-filetype-csv"></i></span>
                        <div>
                            <h2 class="h6 fw-semibold mb-0">Import / export</h2>
                            <div class="small text-muted">Download your entries as a CSV, edit them in Excel, and import them back — or import entries for another instance.</div>
                        </div>
                    </div>
                    <div class="cb-panel-body">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-4">
                                <a href="{{ route('chatbot.export', $selected->instance_id) }}" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-download me-1"></i>{{ $rules->isEmpty() ? 'Download empty template' : 'Export '.$rules->count().' '.Str::plural('entry', $rules->count()) }}
                                </a>
                            </div>
                            <div class="col-md-8">
                                <form method="POST" action="{{ route('chatbot.import', $selected->instance_id) }}" enctype="multipart/form-data">
                                    @csrf
                                    <label for="cb-csv" class="visually-hidden">Import a CSV</label>
                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <div class="flex-grow-1">
                                            <input type="file" id="cb-csv" name="csv" accept=".csv,text/csv" required
                                                   class="form-control @error('csv') is-invalid @enderror">
                                            @if ($errors->has('csv'))
                                                <div class="invalid-feedback">
                                                    @foreach ($errors->get('csv') as $message)
                                                        <div>{{ $message }}</div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary text-nowrap align-self-start"><i class="bi bi-upload me-1"></i>Import</button>
                                    </div>
                                    <div class="form-text">
                                        Columns: <code>question</code>, <code>keywords</code> (comma-separated), <code>answer</code>, and optional <code>status</code> (on/off).
                                        A row with the same question as an existing entry updates it. Attached files aren't included.
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($rules->isEmpty())
                <div class="cb-panel text-center py-5 px-4">
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
            @else
                <div class="cb-list">
                    @foreach ($rules as $rule)
                        <div id="rule-{{ $rule->id }}" class="cb-entry {{ $rule->enabled ? '' : 'is-off' }}">
                            <div class="cb-entry-main">
                                <div class="cb-entry-head">
                                    <span class="fw-semibold text-break">{{ $loop->iteration }}. {{ $rule->question }}</span>
                                    @if ($rule->in_menu)
                                        <span class="cb-pill cb-pill-on" title="Shown in the numbered menu"><i class="bi bi-list-ol"></i>Menu</span>
                                    @endif
                                    @unless ($rule->enabled)
                                        <span class="cb-pill"><i class="bi bi-pause-circle"></i>OFF</span>
                                    @endunless
                                </div>
                                <div class="cb-keywords">
                                    @foreach ($rule->keywords as $keyword)
                                        <span class="cb-keyword">{{ $keyword }}</span>
                                    @endforeach
                                </div>
                                <div class="cb-answer">{{ $rule->answer }}</div>
                                @include('chatbot._attachment', ['rule' => $rule])
                                <div class="cb-entry-meta">
                                    <span>
                                        <i class="bi bi-bar-chart me-1"></i>
                                        @if ($rule->replies_total > 0)
                                            Replied {{ number_format($rule->replies_total) }} {{ Str::plural('time', $rule->replies_total) }} · {{ number_format($rule->replies_week) }} in 7 days · last {{ \Illuminate\Support\Carbon::parse($rule->last_reply_at)->diffForHumans() }}
                                        @else
                                            Not used yet
                                        @endif
                                    </span>
                                    <span><i class="bi bi-pencil me-1"></i>{{ $rule->updated_at->format('M j') }}</span>
                                </div>
                            </div>

                            <div class="cb-entry-actions">
                                <a href="{{ route('chatbot.rules.edit', [$selected->instance_id, $rule->id]) }}" class="btn btn-sm btn-outline-primary cb-edit">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                                <div class="cb-icon-group">
                                    <form method="POST" action="{{ route('chatbot.rules.toggle', [$selected->instance_id, $rule->id]) }}">
                                        @csrf
                                        <input type="hidden" name="enabled" value="{{ $rule->enabled ? 0 : 1 }}">
                                        <button type="submit" class="cb-icon-btn {{ $rule->enabled ? 'is-on' : '' }}"
                                                aria-label="{{ $rule->enabled ? 'Switch off' : 'Switch on' }}: {{ $rule->question }}"
                                                title="{{ $rule->enabled ? 'Switch off (the bot skips it)' : 'Switch on' }}">
                                            <i class="bi {{ $rule->enabled ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    </form>
                                    @if ($rules->count() > 1)
                                        @foreach (['up' => ['bi-arrow-up', 'Move up', $loop->first], 'down' => ['bi-arrow-down', 'Move down', $loop->last]] as $direction => [$icon, $label, $disabled])
                                            <form method="POST" action="{{ route('chatbot.rules.move', [$selected->instance_id, $rule->id]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="{{ $direction }}">
                                                <button type="submit" class="cb-icon-btn" @disabled($disabled)
                                                        aria-label="{{ $label }}: {{ $rule->question }}" title="{{ $label }}">
                                                    <i class="bi {{ $icon }}"></i>
                                                </button>
                                            </form>
                                        @endforeach
                                    @endif
                                    <form method="POST" action="{{ route('chatbot.rules.destroy', [$selected->instance_id, $rule->id]) }}"
                                          onsubmit="return confirm('Delete this entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cb-icon-btn cb-icon-danger" aria-label="Delete {{ $rule->question }}" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="small text-muted mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>If a message matches more than one entry, the one with the most matching keywords answers. On a tie, the higher one in this list wins — use the <i class="bi bi-arrow-up"></i> <i class="bi bi-arrow-down"></i> buttons to change the order.
                </p>
            @endif
        </div>

        {{-- ================================================================ Test bot --}}
        <div class="tab-pane fade {{ $tab === 'test' ? 'show active' : '' }}" id="test" role="tabpanel" aria-labelledby="test-tab" tabindex="0">
            @php
                // Quick examples: the menu word, then each entry's first keyword.
                $tries = collect($menu->enabled ? [$menu->keywords[0] ?? null] : [])
                    ->merge($rules->where('enabled', true)->map(fn ($rule) => $rule->keywords[0] ?? null))
                    ->filter()->unique()->take(8);
            @endphp
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="cb-chat">
                        <div class="cb-chat-head">
                            <span class="cb-chat-avatar"><i class="bi bi-robot"></i></span>
                            <div style="min-width: 0;">
                                <div class="fw-semibold text-break">{{ $selected->name }}</div>
                                <div class="small opacity-75">Test mode — nothing is sent on WhatsApp</div>
                            </div>
                        </div>

                        <div class="cb-chat-body">
                            @if ($rules->isEmpty() && ! $menu->enabled)
                                <div class="cb-chat-note">Add an entry first, then type a message here to see what the bot would reply.</div>
                            @elseif (! $test)
                                <div class="cb-chat-note">Type a message as if you were a customer and see what the bot would reply.</div>
                            @else
                                <div class="cb-msg cb-msg-in">{{ $test['message'] }}</div>

                                @if (array_key_exists('menu_choice', $test))
                                    {{-- The numbered menu handles this message (it's checked before keywords). --}}
                                    @if ($test['menu_text'] !== null)
                                        <div class="cb-chat-why"><i class="bi bi-list-ol me-1"></i>Menu word — the bot sends the numbered menu:</div>
                                        <div class="cb-msg cb-msg-out">{{ \App\Support\ChatbotMenu::previewHtml($test['menu_text']) }}</div>
                                    @elseif ($test['handoff'])
                                        <div class="cb-chat-why"><i class="bi bi-person-raised-hand me-1"></i>Option 0 — Talk to a person:</div>
                                        <div class="cb-msg cb-msg-out">{{ $menu->humanReply }}</div>
                                        <div class="cb-chat-note">The bot then stays quiet in that chat and you get an email.</div>
                                    @elseif ($testRule)
                                        <div class="cb-chat-why"><i class="bi bi-check-circle me-1"></i>Option {{ $test['menu_choice'] }} — {{ $testRule->question }}</div>
                                        <div class="cb-msg cb-msg-out">{{ $testRule->answer }}@if ($testRule->hasMedia())<div class="mt-1">@include('chatbot._attachment', ['rule' => $testRule])</div>@endif</div>
                                    @else
                                        <div class="cb-chat-note"><i class="bi bi-arrow-repeat me-1"></i>{{ $test['menu_choice'] }} isn't on the menu — the bot sends the menu again.</div>
                                    @endif
                                    @if ($test['menu_text'] === null)
                                        <div class="cb-chat-note"><i class="bi bi-info-circle me-1"></i>A number only counts within {{ \App\Support\ChatbotMenu::VALID_MINUTES }} minutes of the customer getting the menu; otherwise it's matched like any other message.</div>
                                    @endif
                                @elseif ($testRule)
                                    <div class="cb-chat-why">
                                        <i class="bi bi-check-circle me-1"></i>Matched entry {{ $rules->search(fn ($r) => $r->id === $testRule->id) + 1 }}: {{ $testRule->question }}
                                        @if (! empty($test['keywords']))
                                            <span class="d-block mt-1">
                                                Keywords found:
                                                @foreach ($test['keywords'] as $keyword)
                                                    <span class="cb-keyword">{{ $keyword }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>
                                    <div class="cb-msg cb-msg-out">{{ $testRule->answer }}@if ($testRule->hasMedia())<div class="mt-1">@include('chatbot._attachment', ['rule' => $testRule])</div>@endif</div>
                                @else
                                    <div class="cb-chat-note">
                                        <i class="bi bi-slash-circle me-1"></i>No keyword matched — the bot would stay silent and leave this message for you.
                                        @if ($offRule = $rules->firstWhere('id', $test['off_rule_id'] ?? null))
                                            <span class="d-block mt-1"><i class="bi bi-pause-circle me-1"></i>"{{ $offRule->question }}" would match, but it's switched off.</span>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>

                        <form method="POST" action="{{ route('chatbot.test', $selected->instance_id) }}" class="cb-chat-input">
                            @csrf
                            <label for="cb-test" class="visually-hidden">Test message</label>
                            <input type="text" id="cb-test" name="test_message" maxlength="4096" required autocomplete="off"
                                   value="{{ old('test_message') }}" placeholder="e.g. What is the price?"
                                   class="form-control @error('test_message') is-invalid @enderror">
                            <button type="submit" class="cb-send" aria-label="Test"><i class="bi bi-send-fill"></i></button>
                        </form>
                        @error('test_message') <div class="small text-danger px-3 pb-2">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="cb-panel cb-try">
                        <h2 class="h6 fw-semibold mb-1">Try these</h2>
                        <div class="small text-muted mb-3">Tap one to see the reply. Customers' messages only need to contain a keyword.</div>
                        @if ($tries->isEmpty())
                            <div class="small text-muted">Your keywords appear here once you add an entry.</div>
                        @else
                            <form method="POST" action="{{ route('chatbot.test', $selected->instance_id) }}" class="d-flex flex-wrap gap-2">
                                @csrf
                                @foreach ($tries as $try)
                                    <button type="submit" name="test_message" value="{{ $try }}" class="cb-try-chip">
                                        <i class="bi bi-chat-left-text"></i>{{ $try }}
                                    </button>
                                @endforeach
                            </form>
                        @endif
                        <hr class="my-3">
                        <div class="small text-muted">
                            <i class="bi bi-lightbulb me-1"></i>The bot checks the numbered menu first, then your entries' keywords. Messages that match nothing are left for you{{ $hours->enabled ? ', or get your "closed" message outside business hours' : '' }}.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================ Settings --}}
        <div class="tab-pane fade {{ $tab === 'settings' ? 'show active' : '' }}" id="settings" role="tabpanel" aria-labelledby="settings-tab" tabindex="0">
            <div class="cb-panel">
                {{-- Business hours --}}
                @php $oldDays = array_map('intval', old('days', $hours->days)); @endphp
                <div id="hours" class="cb-setting">
                    <div class="cb-setting-row">
                        <span class="cb-setting-icon ms-tone-purple"><i class="bi bi-clock"></i></span>
                        <div class="cb-setting-text">
                            <div class="fw-semibold">
                                Business hours
                                @if ($hours->enabled)
                                    <span class="cb-pill cb-pill-on ms-1">ON</span>
                                @else
                                    <span class="cb-pill ms-1">OFF</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                @if ($hours->enabled)
                                    Open {{ $hours->summary() }} ({{ $hours->timezone }}). Outside these hours, messages that match no entry get your "closed" message.
                                @else
                                    Optional: send a "we're closed" message to people who write outside your working hours.
                                @endif
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border flex-shrink-0" data-bs-toggle="collapse" data-bs-target="#hours-form"
                                aria-expanded="{{ $hoursErrors ? 'true' : 'false' }}" aria-controls="hours-form">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                    </div>

                    <div id="hours-form" class="collapse {{ $hoursErrors ? 'show' : '' }}">
                        <form method="POST" action="{{ route('chatbot.hours.update', $selected->instance_id) }}" class="cb-setting-form">
                            @csrf
                            @method('PUT')

                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" role="switch" id="hours-enabled" name="hours_enabled" value="1"
                                       @checked(old('hours_enabled', $hours->enabled))>
                                <label class="form-check-label" for="hours-enabled">Use business hours</label>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-label fw-semibold mb-1">Open days</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach (\App\Support\ChatbotHours::DAYS as $number => $day)
                                            <input type="checkbox" class="btn-check" id="day-{{ $number }}" name="days[]" value="{{ $number }}" autocomplete="off"
                                                   @checked(in_array($number, $oldDays, true))>
                                            <label class="btn btn-sm btn-outline-primary cb-day" for="day-{{ $number }}">{{ $day }}</label>
                                        @endforeach
                                    </div>
                                    @error('days') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="hours-open" class="form-label fw-semibold">Opens at</label>
                                    <input type="time" id="hours-open" name="open" required value="{{ old('open', $hours->open) }}"
                                           class="form-control @error('open') is-invalid @enderror">
                                    @error('open') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="hours-close" class="form-label fw-semibold">Closes at</label>
                                    <input type="time" id="hours-close" name="close" required value="{{ old('close', $hours->close) }}"
                                           class="form-control @error('close') is-invalid @enderror">
                                    @error('close') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="hours-timezone" class="form-label fw-semibold">Timezone</label>
                                    <select id="hours-timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                                        @foreach (\DateTimeZone::listIdentifiers() as $tz)
                                            <option value="{{ $tz }}" @selected(old('timezone', $hours->timezone) === $tz)>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                    @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <div class="form-text mt-0">Closing earlier than opening means overnight — e.g. 20:00 to 02:00.</div>
                                </div>
                                <div class="col-12">
                                    <label for="hours-message" class="form-label fw-semibold">"We're closed" message</label>
                                    <textarea id="hours-message" name="message" rows="3" maxlength="1000"
                                              class="form-control @error('message') is-invalid @enderror">{{ old('message', $hours->message) }}</textarea>
                                    @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">Sent at most once every {{ \App\Models\ChatbotRule::CLOSED_MESSAGE_WAIT_HOURS }} hours to the same person. Messages that match an entry still get that entry's answer.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save hours</button>
                        </form>
                    </div>
                </div>

                {{-- Numbered menu --}}
                <div id="menu" class="cb-setting">
                    <div class="cb-setting-row">
                        <span class="cb-setting-icon ms-tone-green"><i class="bi bi-list-ol"></i></span>
                        <div class="cb-setting-text">
                            <div class="fw-semibold">
                                Numbered menu
                                @if ($menu->enabled)
                                    <span class="cb-pill cb-pill-on ms-1">ON</span>
                                @else
                                    <span class="cb-pill ms-1">OFF</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                @if ($menu->enabled)
                                    When a customer sends <strong>{{ implode(', ', $menu->keywords) }}</strong>, they get a numbered list and reply with a number.
                                    {{ $menuOptions->count() }} {{ Str::plural('option', $menuOptions->count()) }}{{ $menu->humanOption ? ' + "Talk to a person"' : '' }}.
                                @else
                                    Optional: customers type "menu" and pick an answer by number — "Reply 1 for prices, 2 for timings".
                                @endif
                            </div>
                            @error('menu_enabled') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                        </div>
                        <button type="button" class="btn btn-sm btn-light border flex-shrink-0" data-bs-toggle="collapse" data-bs-target="#menu-form"
                                aria-expanded="{{ $menuErrors ? 'true' : 'false' }}" aria-controls="menu-form">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                    </div>

                    <div id="menu-form" class="collapse {{ $menuErrors ? 'show' : '' }}">
                        <div class="row g-4 cb-setting-form">
                            <div class="col-lg-7">
                                <form method="POST" action="{{ route('chatbot.menu.update', $selected->instance_id) }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="form-check form-switch mb-3">
                                        <input type="checkbox" class="form-check-input" role="switch" id="menu-enabled" name="menu_enabled" value="1"
                                               @checked(old('menu_enabled', $menu->enabled))>
                                        <label class="form-check-label" for="menu-enabled">Use the numbered menu</label>
                                    </div>

                                    <div class="mb-3">
                                        <label for="menu-intro" class="form-label fw-semibold">First line</label>
                                        <input type="text" id="menu-intro" name="intro" maxlength="500" required value="{{ old('intro', $menu->intro) }}"
                                               class="form-control @error('intro') is-invalid @enderror">
                                        @error('intro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">Shown in bold above the numbered options, e.g. "Welcome to ABC Shoes! How can we help?". A "Reply with a number" hint is added at the bottom for you.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="menu-keywords" class="form-label fw-semibold">Menu words</label>
                                        <input type="text" id="menu-keywords" name="menu_keywords" maxlength="500" required
                                               value="{{ old('menu_keywords', implode(', ', $menu->keywords)) }}"
                                               class="form-control @error('menu_keywords') is-invalid @enderror">
                                        @error('menu_keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">Separate with commas. A message with one of these gets the menu — before any entry's keywords, so don't use a word an entry needs.</div>
                                    </div>

                                    <div class="form-check form-switch mb-2">
                                        <input type="checkbox" class="form-check-input" role="switch" id="menu-human" name="human_option" value="1"
                                               @checked(old('human_option', $menu->humanOption))>
                                        <label class="form-check-label" for="menu-human">Add <strong>"0. Talk to a person"</strong></label>
                                    </div>
                                    <div class="mb-1">
                                        <label for="menu-human-reply" class="form-label small fw-semibold mb-1">Reply when they choose 0</label>
                                        <textarea id="menu-human-reply" name="human_reply" rows="2" maxlength="1000"
                                                  class="form-control @error('human_reply') is-invalid @enderror">{{ old('human_reply', $menu->humanReply) }}</textarea>
                                        @error('human_reply') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">Then the bot stays quiet in that chat ({{ $pauseLabel ?? '1 hour' }}) and you get an email so you can reply.</div>
                                    </div>

                                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save menu</button>
                                </form>
                            </div>

                            <div class="col-lg-5">
                                <div class="cb-preview">
                                    <div class="small fw-semibold mb-2">What customers see</div>
                                    <div class="cb-msg cb-msg-out">{{ \App\Support\ChatbotMenu::previewHtml($menu->text($menuOptions)) }}</div>
                                    <div class="small text-muted mt-2">
                                        @if ($menuOptions->isEmpty())
                                            <i class="bi bi-exclamation-circle text-warning me-1"></i>No options yet — tick <strong>"Show in the numbered menu"</strong> on the entries you want listed.
                                        @else
                                            Options are the entries ticked <strong>"Show in the numbered menu"</strong>, in your list order (max {{ \App\Support\ChatbotMenu::MAX_OPTIONS }}). Customers reply with a number within {{ \App\Support\ChatbotMenu::VALID_MINUTES }} minutes.
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pause when the owner replies by hand --}}
                <div id="pause" class="cb-setting">
                    <div class="cb-setting-row">
                        <span class="cb-setting-icon ms-tone-blue"><i class="bi bi-person-raised-hand"></i></span>
                        <div class="cb-setting-text">
                            <div class="fw-semibold">Pause when you reply yourself</div>
                            <div class="small text-muted">When you answer a customer from your phone, the bot stays quiet in that chat so it doesn't interrupt you.</div>
                        </div>
                        <form method="POST" action="{{ route('chatbot.pause.update', $selected->instance_id) }}" class="cb-pause-form">
                            @csrf
                            @method('PUT')
                            <label for="pause-minutes" class="visually-hidden">Pause for</label>
                            <select id="pause-minutes" name="pause_minutes" class="form-select form-select-sm @error('pause_minutes') is-invalid @enderror">
                                @foreach (\App\Models\ChatbotPause::DURATIONS as $minutes => $label)
                                    <option value="{{ $minutes }}" @selected($selected->chatbot_pause_minutes === $minutes)>{{ $minutes > 0 ? "Pause for {$label}" : $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-light border">Save</button>
                        </form>
                    </div>
                    @error('pause_minutes') <div class="small text-danger cb-setting-form pt-0">{{ $message }}</div> @enderror

                    @if ($pauses->isNotEmpty())
                        <div class="cb-setting-form">
                            <div class="small fw-semibold mb-2">Paused right now</div>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                @foreach ($pauses as $pause)
                                    <li class="cb-paused">
                                        <span class="text-break"><i class="bi bi-pause-circle text-muted me-1"></i><span class="fw-medium">+{{ $pause->phone }}</span> <span class="text-muted">· {{ $pause->isTurnedOff() ? 'turned off in the Inbox' : 'answers again '.$pause->paused_until->diffForHumans() }}</span></span>
                                        <form method="POST" action="{{ route('chatbot.pauses.destroy', [$selected->instance_id, $pause->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link p-0">Resume now</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ================================================================ Unanswered --}}
        <div class="tab-pane fade {{ $tab === 'unanswered' ? 'show active' : '' }}" id="unanswered" role="tabpanel" aria-labelledby="unanswered-tab" tabindex="0">
            <div class="cb-panel">
                <div class="cb-panel-head">
                    <span class="cb-panel-icon"><i class="bi bi-question-circle"></i></span>
                    <div>
                        <h2 class="h6 fw-semibold mb-0">Unanswered questions</h2>
                        <div class="small text-muted">
                            Messages from the last 7 days that none of your entries would answer. Add an entry for the common ones — they disappear from here once an entry matches.
                        </div>
                    </div>
                </div>

                @if ($unanswered->isEmpty())
                    <div class="cb-panel-body small text-muted"><i class="bi bi-check-circle me-1 text-success"></i>Nothing here — every recent message matches an entry (or no messages yet).</div>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach ($unanswered as $question)
                            <li class="cb-question">
                                <div style="min-width: 0;">
                                    <div class="text-break">{{ Str::limit($question['body'], 200) }}</div>
                                    <div class="small text-muted">
                                        +{{ $question['from'] }} · {{ $question['last_at']->diffForHumans() }}
                                        @if ($question['count'] > 1)
                                            · <span class="fw-medium">asked {{ $question['count'] }} times</span>
                                        @endif
                                    </div>
                                </div>
                                @if ($planLimit > 0 && $canAdd)
                                    <a href="{{ route('chatbot.index', ['instance' => $selected->instance_id, 'question' => Str::limit($question['body'], 150, '')]) }}#new-entry"
                                       class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0">
                                        <i class="bi bi-plus-lg me-1"></i>Add as entry
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endif
@endsection
