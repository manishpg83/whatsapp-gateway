@extends('layouts.app')

@section('title', 'Chatbot')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 db-in">
    <div class="d-flex align-items-center gap-3">
        <span class="ms-head-icon"><i class="bi bi-robot"></i></span>
        <div>
            <h1 class="h3 mb-0">Chatbot</h1>
            <div class="text-muted small">
                Answer customers automatically when their message has one of your keywords. Other messages are left for you.
                <a href="{{ route('guides.show', 'whatsapp-auto-reply-chatbot') }}" target="_blank" rel="noopener" class="text-nowrap">
                    <i class="bi bi-question-circle me-1"></i>How it works
                </a>
            </div>
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

    {{-- Stats for this instance --}}
    <div class="row g-2 g-sm-3 mb-4 cb-stats">
        @foreach ([
            ['Answers sent · 7 days', $stats['answers_week'], 'bi-robot', 'green'],
            ['"Closed" messages · 7 days', $stats['closed_week'], 'bi-moon-stars', 'purple'],
            ['All bot replies · 30 days', $stats['month'], 'bi-graph-up', 'blue'],
        ] as $i => [$label, $count, $icon, $tone])
            <div class="col-4">
                <div class="ms-tile ms-tone-{{ $tone }} db-in" style="--i: {{ $i + 2 }};">
                    <span class="ms-tile-icon"><i class="bi {{ $icon }}"></i></span>
                    <span class="d-block" style="min-width: 0;">
                        <span class="ms-tile-label">{{ $label }}</span>
                        <span class="ms-tile-value">{{ number_format($count) }}</span>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Business hours (collapsed until "Edit" is clicked, or a field has an error) --}}
    @php
        $hours = $selected->chatbotHours();
        $hoursErrors = $errors->hasAny(['days', 'days.*', 'open', 'close', 'timezone', 'message']);
        $oldDays = array_map('intval', old('days', $hours->days));
    @endphp
    <div id="hours" class="card shadow-sm mb-4 db-in" style="--i: 2;">
        <div class="card-body">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">
                        <i class="bi bi-clock me-1 text-primary"></i>Business hours
                        @if ($hours->enabled)
                            <span class="badge rounded-pill bg-wa-light text-primary border ms-1">ON</span>
                        @else
                            <span class="badge rounded-pill text-bg-light border ms-1">OFF</span>
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
                <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-bs-toggle="collapse" data-bs-target="#hours-form"
                        aria-expanded="{{ $hoursErrors ? 'true' : 'false' }}" aria-controls="hours-form">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
            </div>

            <div id="hours-form" class="collapse {{ $hoursErrors ? 'show' : '' }}">
                <form method="POST" action="{{ route('chatbot.hours.update', $selected->instance_id) }}" class="border-top mt-3 pt-3">
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
                                    <label class="btn btn-sm btn-outline-primary" for="day-{{ $number }}">{{ $day }}</label>
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
    </div>

    {{-- Numbered menu (collapsed until "Edit" is clicked, or a field has an error) --}}
    @php
        $menu = $selected->chatbotMenu();
        $menuOptions = \App\Support\ChatbotMenu::options($rules);
        $menuErrors = $errors->hasAny(['menu_enabled', 'intro', 'menu_keywords', 'human_reply']);
    @endphp
    <div id="menu" class="card shadow-sm mb-4 db-in" style="--i: 2;">
        <div class="card-body">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">
                        <i class="bi bi-list-ol me-1 text-primary"></i>Numbered menu
                        @if ($menu->enabled)
                            <span class="badge rounded-pill bg-wa-light text-primary border ms-1">ON</span>
                        @else
                            <span class="badge rounded-pill text-bg-light border ms-1">OFF</span>
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
                <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-bs-toggle="collapse" data-bs-target="#menu-form"
                        aria-expanded="{{ $menuErrors ? 'true' : 'false' }}" aria-controls="menu-form">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
            </div>

            <div id="menu-form" class="collapse {{ $menuErrors ? 'show' : '' }}">
                <div class="row g-4 border-top mt-3 pt-1">
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
                                <div class="form-text">Then the bot stays quiet in that chat ({{ $selected->chatbot_pause_minutes > 0 ? \App\Models\ChatbotPause::DURATIONS[$selected->chatbot_pause_minutes] ?? $selected->chatbot_pause_minutes.' minutes' : '1 hour' }}) and you get an email so you can reply.</div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save menu</button>
                        </form>
                    </div>

                    <div class="col-lg-5">
                        <div class="small fw-semibold mb-2">What customers see</div>
                        <div class="bk-bubble bk-bubble-text">{{ \App\Support\ChatbotMenu::previewHtml($menu->text($menuOptions)) }}</div>
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
    <div id="pause" class="card shadow-sm mb-4 db-in" style="--i: 2;">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold"><i class="bi bi-person-raised-hand me-1 text-primary"></i>Pause when you reply yourself</div>
                    <div class="small text-muted">When you answer a customer from your phone, the bot stays quiet in that chat so it doesn't interrupt you.</div>
                </div>
                <form method="POST" action="{{ route('chatbot.pause.update', $selected->instance_id) }}" class="d-flex gap-2 flex-shrink-0">
                    @csrf
                    @method('PUT')
                    <label for="pause-minutes" class="visually-hidden">Pause for</label>
                    <select id="pause-minutes" name="pause_minutes" class="form-select form-select-sm">
                        @foreach (\App\Models\ChatbotPause::DURATIONS as $minutes => $label)
                            <option value="{{ $minutes }}" @selected($selected->chatbot_pause_minutes === $minutes)>{{ $minutes > 0 ? "Pause for {$label}" : $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                </form>
            </div>

            @if ($pauses->isNotEmpty())
                <div class="border-top mt-3 pt-3">
                    <div class="small fw-semibold mb-2">Paused right now</div>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                        @foreach ($pauses as $pause)
                            <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                                <span><i class="bi bi-pause-circle text-muted me-1"></i><span class="fw-medium">+{{ $pause->phone }}</span> <span class="text-muted">· answers again {{ $pause->paused_until->diffForHumans() }}</span></span>
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

    <div class="row g-4">
        {{-- New entry --}}
        <div class="col-lg-4">
            <div id="new-entry" class="card shadow-sm db-in" style="--i: 2;">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-1"><i class="bi bi-plus-circle me-1 text-primary"></i>New entry</h2>
                    <div class="small text-muted mb-3 text-break">
                        For {{ $selected->name }} · {{ number_format($planUsed) }} / {{ number_format($planLimit) }} entries used on your plan
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
                    <form method="POST" action="{{ route('chatbot.rules.store', $selected->instance_id) }}" enctype="multipart/form-data">
                        @csrf
                        @include('chatbot._fields', ['rule' => null, 'prefill' => $prefillQuestion])
                        <button type="submit" class="btn btn-primary w-100 mt-3" @disabled($rules->count() >= $max || $planUsed >= $planLimit)>
                            <i class="bi bi-plus-lg me-1"></i>Add entry
                        </button>
                    </form>
                </div>
            </div>

            {{-- Import / export (CSV) --}}
            <div id="csv" class="card shadow-sm mt-4 db-in" style="--i: 3;">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-1"><i class="bi bi-filetype-csv me-1 text-primary"></i>Import / export</h2>
                    <div class="small text-muted mb-3">
                        Download your entries as a CSV, edit them in Excel, and import them back — or import entries for another instance.
                    </div>

                    <a href="{{ route('chatbot.export', $selected->instance_id) }}" class="btn btn-sm btn-outline-primary w-100 mb-3">
                        <i class="bi bi-download me-1"></i>{{ $rules->isEmpty() ? 'Download empty template' : 'Export '.$rules->count().' '.Str::plural('entry', $rules->count()) }}
                    </a>

                    <form method="POST" action="{{ route('chatbot.import', $selected->instance_id) }}" enctype="multipart/form-data">
                        @csrf
                        <label for="cb-csv" class="form-label small fw-semibold">Import a CSV</label>
                        <input type="file" id="cb-csv" name="csv" accept=".csv,text/csv" required
                               class="form-control form-control-sm @error('csv') is-invalid @enderror">
                        @if ($errors->has('csv'))
                            <div class="invalid-feedback">
                                @foreach ($errors->get('csv') as $message)
                                    <div>{{ $message }}</div>
                                @endforeach
                            </div>
                        @endif
                        <div class="form-text">
                            Columns: <code>question</code>, <code>keywords</code> (comma-separated), <code>answer</code>, and optional <code>status</code> (on/off).
                            A row with the same question as an existing entry updates it. Attached files aren't included.
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2"><i class="bi bi-upload me-1"></i>Import</button>
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
                                @if (array_key_exists('menu_choice', $test))
                                    {{-- The numbered menu handles this message (it's checked before keywords). --}}
                                    @if ($test['menu_text'] !== null)
                                        <div class="small text-success fw-semibold mb-2"><i class="bi bi-list-ol me-1"></i>Menu word — the bot sends the numbered menu:</div>
                                        <div class="bk-bubble bk-bubble-text">{{ \App\Support\ChatbotMenu::previewHtml($test['menu_text']) }}</div>
                                    @elseif ($test['handoff'])
                                        <div class="small text-success fw-semibold mb-2"><i class="bi bi-person-raised-hand me-1"></i>Option 0 — Talk to a person:</div>
                                        <div class="bk-bubble bk-bubble-text">{{ $selected->chatbotMenu()->humanReply }}</div>
                                        <div class="small text-muted mt-2">The bot then stays quiet in that chat and you get an email.</div>
                                    @elseif ($testRule)
                                        <div class="small text-success fw-semibold mb-2"><i class="bi bi-check-circle me-1"></i>Option {{ $test['menu_choice'] }} — {{ $testRule->question }}</div>
                                        <div class="bk-bubble bk-bubble-text">{{ $testRule->answer }}</div>
                                        @include('chatbot._attachment', ['rule' => $testRule])
                                    @else
                                        <div class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i>{{ $test['menu_choice'] }} isn't on the menu — the bot sends the menu again.</div>
                                    @endif
                                    @if ($test['menu_text'] === null)
                                        <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>A number only counts within {{ \App\Support\ChatbotMenu::VALID_MINUTES }} minutes of the customer getting the menu; otherwise it's matched like any other message.</div>
                                    @endif
                                @elseif ($testRule)
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
                                    @include('chatbot._attachment', ['rule' => $testRule])
                                @else
                                    <div class="small text-muted">
                                        <i class="bi bi-slash-circle me-1"></i>No keyword matched — the bot would stay silent and leave this message for you.
                                    </div>
                                    @if ($offRule = $rules->firstWhere('id', $test['off_rule_id'] ?? null))
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-pause-circle me-1"></i>"{{ $offRule->question }}" would match, but it's switched off.
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <p class="small text-muted mb-3 db-in" style="--i: 4;">
                    <i class="bi bi-info-circle me-1"></i>If a message matches more than one entry, the one with the most matching keywords answers. On a tie, the higher one in this list wins — use the <i class="bi bi-arrow-up"></i> <i class="bi bi-arrow-down"></i> buttons to change the order.
                </p>
                <div class="row g-3">
                    @foreach ($rules as $rule)
                        <div id="rule-{{ $rule->id }}" class="col-md-6">
                            <div class="bk-template-card db-in" style="--i: {{ min($loop->iteration + 3, 12) }};">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold text-break">
                                        {{ $loop->iteration }}. {{ $rule->question }}
                                        @if ($rule->in_menu)
                                            <span class="badge rounded-pill bg-wa-light text-primary border ms-1" title="Shown in the numbered menu"><i class="bi bi-list-ol me-1"></i>Menu</span>
                                        @endif
                                        @unless ($rule->enabled)
                                            <span class="badge rounded-pill text-bg-light border ms-1"><i class="bi bi-pause-circle me-1"></i>OFF</span>
                                        @endunless
                                    </div>
                                    <span class="small text-muted text-nowrap">{{ $rule->updated_at->format('M j') }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($rule->keywords as $keyword)
                                        <span class="badge rounded-pill text-bg-light border text-break">{{ $keyword }}</span>
                                    @endforeach
                                </div>
                                {{-- Faded while switched off. --}}
                                <div class="bk-bubble bk-bubble-text {{ $rule->enabled ? '' : 'opacity-50' }}">{{ $rule->answer }}</div>
                                @include('chatbot._attachment', ['rule' => $rule])
                                <div class="small text-muted">
                                    <i class="bi bi-bar-chart me-1"></i>
                                    @if ($rule->replies_total > 0)
                                        Replied {{ number_format($rule->replies_total) }} {{ Str::plural('time', $rule->replies_total) }} · {{ number_format($rule->replies_week) }} in 7 days · last {{ \Illuminate\Support\Carbon::parse($rule->last_reply_at)->diffForHumans() }}
                                    @else
                                        Not used yet
                                    @endif
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('chatbot.rules.edit', [$selected->instance_id, $rule->id]) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    <form method="POST" action="{{ route('chatbot.rules.toggle', [$selected->instance_id, $rule->id]) }}">
                                        @csrf
                                        <input type="hidden" name="enabled" value="{{ $rule->enabled ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm {{ $rule->enabled ? 'btn-outline-secondary' : 'btn-outline-primary' }}"
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
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($disabled)
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

    {{-- Received messages that no entry would answer right now --}}
    <div id="unanswered" class="card shadow-sm mt-4 db-in" style="--i: 4;">
        <div class="card-body">
            <h2 class="h6 fw-semibold mb-1"><i class="bi bi-question-circle me-1 text-primary"></i>Unanswered questions</h2>
            <div class="small text-muted mb-3">
                Messages from the last 7 days that none of your entries would answer. Add an entry for the common ones — they disappear from here once an entry matches.
            </div>

            @if ($unanswered->isEmpty())
                <div class="small text-muted"><i class="bi bi-check-circle me-1 text-success"></i>Nothing here — every recent message matches an entry (or no messages yet).</div>
            @else
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                    @foreach ($unanswered as $question)
                        <li class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 border-bottom pb-2">
                            <div style="min-width: 0;">
                                <div class="text-break">{{ Str::limit($question['body'], 200) }}</div>
                                <div class="small text-muted">
                                    +{{ $question['from'] }} · {{ $question['last_at']->diffForHumans() }}
                                    @if ($question['count'] > 1)
                                        · <span class="fw-medium">asked {{ $question['count'] }} times</span>
                                    @endif
                                </div>
                            </div>
                            @if ($planLimit > 0 && $planUsed < $planLimit && $rules->count() < $max)
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
@endif
@endsection
