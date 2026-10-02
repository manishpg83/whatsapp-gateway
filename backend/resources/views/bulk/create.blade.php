@extends('layouts.app')

@section('title', 'New bulk campaign')

@section('content')
@php
    $connected = $instances->where('status', 'connected');
    $limit = min($maxRecipients, $remaining);
@endphp

@vite('resources/js/bulk.js')

<a href="{{ route('bulk.index') }}" class="bk-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Bulk messages
</a>

<div class="d-flex align-items-center gap-3 mb-4 db-in" style="--i: 1;">
    <span class="ms-head-icon"><i class="bi bi-megaphone"></i></span>
    <div>
        <h1 class="h3 mb-0">New campaign</h1>
        <div class="text-muted small">One message — text, image, video or document — to a list of numbers, sent one by one.</div>
    </div>
</div>

@if ($connected->isEmpty())
    <div class="alert alert-warning d-flex gap-2 db-in" style="--i: 2;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            You need a <strong>connected</strong> instance to send messages.
            <a href="{{ route('instances.index') }}" class="alert-link">Go to Instances</a> to connect your WhatsApp number.
        </div>
    </div>
@endif

<form method="POST" action="{{ route('bulk.store') }}" enctype="multipart/form-data" data-bulk-form
      data-interval="{{ old('interval', config('bulk.default_interval')) }}" data-limit="{{ $limit }}">
    @csrf
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm db-in" style="--i: 2;">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Campaign name</label>
                            <input type="text" id="name" name="name" maxlength="100" required
                                   value="{{ old('name') }}" placeholder="e.g. Diwali offer 2026"
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Only you see this.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="instance_id" class="form-label fw-semibold">Send from</label>
                            <select id="instance_id" name="instance_id" required class="form-select @error('instance_id') is-invalid @enderror">
                                <option value="">Choose an instance…</option>
                                @foreach ($instances as $instance)
                                    <option value="{{ $instance->instance_id }}" @selected(old('instance_id') === $instance->instance_id) @disabled($instance->status !== 'connected')>
                                        {{ $instance->name }}{{ $instance->phone_number ? ' ('.$instance->phone_number.')' : '' }}{{ $instance->status !== 'connected' ? ' — not connected' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('instance_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @php $type = old('type', 'text'); @endphp
                    <div class="mt-3">
                        <span class="form-label fw-semibold d-block">What to send</span>
                        <div class="bk-types" role="radiogroup" aria-label="What to send">
                            @foreach (\App\Models\BulkCampaign::TYPES as $value => [$typeLabel, $typeIcon])
                                <input type="radio" class="btn-check" name="type" id="type-{{ $value }}" value="{{ $value }}" @checked($type === $value) data-bulk-type>
                                <label class="bk-type" for="type-{{ $value }}"><i class="bi {{ $typeIcon }}"></i>{{ $typeLabel }}</label>
                            @endforeach
                        </div>
                        @error('type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    {{-- Image / video / document: the file (hidden for Text) --}}
                    <div class="mt-3" data-bulk-media @if ($type === 'text') hidden @endif>
                        <label for="media" class="form-label fw-semibold">File</label>
                        <label for="media" class="bk-drop @error('media') is-invalid @enderror" data-bulk-drop>
                            <span data-bulk-drop-empty>
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span class="fw-semibold">Drop a file here or click to choose</span>
                                <span class="small text-muted" data-bulk-drop-hint>JPG, PNG or WEBP image, up to 5 MB</span>
                            </span>
                            <span class="bk-drop-preview" data-bulk-drop-preview hidden></span>
                        </label>
                        <input type="file" id="media" name="media" class="visually-hidden" data-bulk-file>
                        @error('media') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @if ($errors->any() && $type !== 'text')
                            <div class="form-text text-warning-emphasis"><i class="bi bi-info-circle me-1"></i>Please choose the file again — browsers don't keep it after an error.</div>
                        @endif
                    </div>

                    <div class="mt-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
                            <label for="message" class="form-label fw-semibold mb-0" data-bulk-message-label>{{ $type === 'text' ? 'Message' : 'Caption (optional)' }}</label>
                            <div class="d-flex flex-wrap gap-2">
                                @if ($templates->isNotEmpty())
                                    <select class="form-select form-select-sm bk-template-select" aria-label="Use a saved message" data-bulk-template>
                                        <option value="">Use a saved message…</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                    <script type="application/json" data-bulk-templates>@json($templates->pluck('body', 'id'))</script>
                                @endif
                                <button type="button" class="btn btn-sm btn-light border bk-insert" data-bulk-insert-name title="Insert each person's name">
                                    <i class="bi bi-person-plus me-1"></i>Insert <code>{name}</code>
                                </button>
                            </div>
                        </div>
                        <textarea id="message" name="message" rows="6" maxlength="4096" @required($type === 'text')
                                  placeholder="Hi {name}! Our Diwali sale is live — 20% off everything until Sunday."
                                  class="form-control @error('message') is-invalid @enderror" data-bulk-message>{{ old('message') }}</textarea>
                        @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text d-flex justify-content-between gap-2">
                            <span>WhatsApp formatting works: *bold*, _italic_, ~strike~. <code>{name}</code> becomes each person's name.</span>
                            <span class="text-nowrap"><span data-bulk-chars>0</span> / 4096</span>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" value="1" id="save_template" name="save_template" @checked(old('save_template')) data-bulk-save-template>
                                <label class="form-check-label small" for="save_template">Save this message for later</label>
                            </div>
                            <input type="text" name="template_name" maxlength="100" value="{{ old('template_name') }}"
                                   placeholder="Name, e.g. Diwali greeting" aria-label="Name for the saved message"
                                   class="form-control form-control-sm bk-template-name @error('template_name') is-invalid @enderror"
                                   data-bulk-template-name @if (! old('save_template')) hidden @endif>
                            <a href="{{ route('bulk.templates.index') }}" class="small ms-auto">Manage saved messages</a>
                        </div>
                        @error('template_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                        {{-- Shown once the message uses {name} --}}
                        <div class="bk-personal mt-3" data-bulk-personal hidden>
                            <div class="row g-3 align-items-start">
                                <div class="col-md-5">
                                    <label for="name_fallback" class="form-label small fw-semibold mb-1">If a number has no name, use</label>
                                    <input type="text" id="name_fallback" name="name_fallback" maxlength="50"
                                           value="{{ old('name_fallback') }}" placeholder="e.g. there, or Customer"
                                           class="form-control form-control-sm @error('name_fallback') is-invalid @enderror" data-bulk-fallback>
                                    @error('name_fallback') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-7">
                                    <span class="form-label small fw-semibold mb-1 d-block">Preview <span class="text-muted fw-normal" data-bulk-preview-who></span></span>
                                    <div class="bk-bubble bk-bubble-text small" data-bulk-preview></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php $source = old('recipients_source', 'paste'); @endphp
                    <div class="mt-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
                            <span class="form-label fw-semibold mb-0">Phone numbers</span>
                            <div class="bk-seg" role="tablist" aria-label="How to add numbers">
                                <input type="radio" class="btn-check" name="recipients_source" id="source-paste" value="paste" @checked($source !== 'csv') data-bulk-source>
                                <label class="bk-seg-btn" for="source-paste"><i class="bi bi-clipboard me-1"></i>Paste</label>
                                <input type="radio" class="btn-check" name="recipients_source" id="source-csv" value="csv" @checked($source === 'csv') data-bulk-source>
                                <label class="bk-seg-btn" for="source-csv"><i class="bi bi-filetype-csv me-1"></i>Upload CSV</label>
                            </div>
                        </div>

                        <div data-bulk-source-panel="paste" @if ($source === 'csv') hidden @endif>
                            <textarea id="numbers" name="numbers" rows="8" @required($source !== 'csv')
                                      placeholder="919876543210, Rahul&#10;919812345678, Priya&#10;14155550123"
                                      class="form-control font-monospace @error('numbers') is-invalid @enderror" data-bulk-numbers>{{ old('numbers') }}</textarea>
                            @error('numbers') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                One per line, with country code (e.g. 91 for India). Add a name after a comma for <code>{name}</code>
                                — or copy two columns (number, name) straight from Excel.
                            </div>
                        </div>

                        <div data-bulk-source-panel="csv" @if ($source !== 'csv') hidden @endif>
                            <label for="csv" class="bk-drop bk-drop-sm @error('csv') is-invalid @enderror" data-bulk-csv-drop>
                                <span>
                                    <i class="bi bi-filetype-csv"></i>
                                    <span class="fw-semibold" data-bulk-csv-name>Choose a CSV file</span>
                                    <span class="small text-muted">Column 1: phone number · Column 2: name (optional) · max 2 MB</span>
                                </span>
                            </label>
                            <input type="file" id="csv" name="csv" accept=".csv,.txt,text/csv" class="visually-hidden" data-bulk-csv>
                            @error('csv') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @if ($errors->any() && $source === 'csv')
                                <div class="form-text text-warning-emphasis"><i class="bi bi-info-circle me-1"></i>Please choose the CSV again — browsers don't keep it after an error.</div>
                            @endif
                            <div class="form-text">
                                <a href="{{ route('bulk.sample') }}"><i class="bi bi-download me-1"></i>Download a sample CSV</a>
                                · In Excel, format the phone column as <strong>Text</strong> so long numbers aren't changed.
                            </div>
                        </div>

                        <div class="form-text d-flex flex-wrap justify-content-between gap-2 mt-1">
                            <span>Duplicates are removed automatically.</span>
                            <span class="text-nowrap" data-bulk-count-wrap>
                                <strong data-bulk-count>0</strong> numbers<span data-bulk-named></span> · up to {{ number_format($limit) }}
                            </span>
                        </div>
                    </div>

                    <div class="row g-3 mt-0 align-items-end">
                        <div class="col-sm-6">
                            <label for="interval" class="form-label fw-semibold">Gap between messages</label>
                            <select id="interval" name="interval" class="form-select @error('interval') is-invalid @enderror" data-bulk-interval>
                                @foreach ($intervals as $seconds)
                                    <option value="{{ $seconds }}" @selected((int) old('interval', config('bulk.default_interval')) === $seconds)>
                                        {{ $seconds }} seconds{{ $seconds === config('bulk.default_interval') ? ' (recommended)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('interval') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <div class="bk-estimate">
                                <i class="bi bi-hourglass-split"></i>
                                <span>Takes about <strong data-bulk-estimate>—</strong></span>
                            </div>
                        </div>
                    </div>

                    @php $when = old('when', 'now'); @endphp
                    <div class="mt-4">
                        <span class="form-label fw-semibold d-block">When to send</span>
                        <div class="bk-seg" role="radiogroup" aria-label="When to send">
                            <input type="radio" class="btn-check" name="when" id="when-now" value="now" @checked($when !== 'later') data-bulk-when>
                            <label class="bk-seg-btn" for="when-now"><i class="bi bi-send me-1"></i>Now</label>
                            <input type="radio" class="btn-check" name="when" id="when-later" value="later" @checked($when === 'later') data-bulk-when>
                            <label class="bk-seg-btn" for="when-later"><i class="bi bi-calendar-event me-1"></i>Schedule for later</label>
                        </div>
                        <div class="row g-2 align-items-center mt-1" data-bulk-schedule @if ($when !== 'later') hidden @endif>
                            <div class="col-sm-6">
                                <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}"
                                       aria-label="Send at" class="form-control @error('scheduled_at') is-invalid @enderror" data-bulk-scheduled-at>
                                @error('scheduled_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6 small text-muted">
                                Your time (<span data-bulk-tz>{{ config('bulk.default_timezone') }}</span>), up to {{ config('bulk.max_schedule_days') }} days ahead.
                            </div>
                        </div>
                        <input type="hidden" name="timezone" value="{{ old('timezone') }}" data-bulk-timezone>
                    </div>

                    <div class="form-check bk-consent mt-4">
                        <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" value="1" id="consent" name="consent" @checked(old('consent')) required>
                        <label class="form-check-label" for="consent">
                            These people agreed to receive WhatsApp messages from me, and I follow the
                            <a href="{{ route('terms') }}#bulk-messaging" target="_blank">bulk messaging rules</a>.
                        </label>
                        @error('consent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex flex-wrap gap-2 p-3 p-md-4 pt-md-3">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" @disabled($connected->isEmpty())>
                        <i class="bi bi-send" data-bulk-submit-icon></i> <span data-bulk-submit-label>{{ $when === 'later' ? 'Schedule campaign' : 'Start sending' }}</span>
                    </button>
                    <a href="{{ route('bulk.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </div>
        </div>

        {{-- Side info --}}
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4 db-in" style="--i: 3;">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-speedometer2 me-1 text-primary"></i>Your limits</h2>
                    <div class="bk-limit">
                        <span>Numbers per campaign</span><strong>{{ number_format($maxRecipients) }}</strong>
                    </div>
                    <div class="bk-limit">
                        <span>Messages left this month</span><strong>{{ number_format($remaining) }}</strong>
                    </div>
                    <div class="small text-muted mt-2">Every message in a campaign counts toward your monthly messages.
                        Need more? <a href="{{ route('billing.index') }}">Upgrade</a>.</div>
                </div>
            </div>

            <div class="card shadow-sm db-in" style="--i: 4;">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-shield-check me-1 text-primary"></i>How it works</h2>
                    <ul class="bk-tips">
                        <li><strong>One at a time.</strong> Messages go out one by one with a gap between them — never all at once.</li>
                        <li><strong>Keep the instance connected.</strong> If it disconnects, the campaign pauses itself and you can resume later.</li>
                        <li><strong>Pause or cancel anytime</strong> from the campaign page. You can close this tab — sending continues.</li>
                        <li><strong>Only message people who expect it.</strong> WhatsApp may restrict numbers that get reported as spam.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
