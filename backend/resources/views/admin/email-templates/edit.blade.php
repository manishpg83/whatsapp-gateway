@extends('layouts.app')

@section('title', 'Admin · Edit ' . $template['label'] . ' email')

@section('content')
@php
    // After a failed save or a test send, show what the admin typed.
    $body = \App\Services\EmailTemplates::clean(old('body', $content['body']));
@endphp

@vite('resources/js/email-editor.js')

<a href="{{ route('admin.email-templates.index') }}" class="ad-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Email templates
</a>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4 db-in" style="--i: 1;">
    <div>
        <h1 class="h3 mb-1">{{ $template['label'] }} email</h1>
        <p class="text-muted mb-0"><i class="bi bi-send me-1"></i>Sent when: {{ $template['sent_when'] }}</p>
    </div>
    <div class="flex-shrink-0">
        @if ($saved)
            <span class="ad-pill ad-tone-blue"><i class="bi bi-pencil"></i>Edited {{ $saved->updated_at->diffForHumans() }}@if ($saved->editor) by {{ $saved->editor->name }}@endif</span>
        @else
            <span class="ad-pill ad-tone-grey">Using the built-in default</span>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('admin.email-templates.update', $key) }}" id="et-form" data-et-form>
    @csrf
    <div class="row g-4">
        {{-- Left: the editor + placeholders --}}
        <div class="col-xl-6">
            <div class="ad-panel h-auto mb-4 db-in" style="--i: 2;">
                <div class="ad-panel-head">
                    <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-pencil-square"></i></span>
                    <div class="fw-semibold">Email content</div>
                </div>
                <div class="p-3 p-md-4">
                    <div class="mb-3">
                        <label for="subject" class="form-label fw-semibold">Subject</label>
                        <input type="text" id="subject" name="subject" maxlength="200" required
                               value="{{ old('subject', $content['subject']) }}"
                               class="form-control @error('subject') is-invalid @enderror" data-et-field>
                        @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="et-editor">Body</label>
                        <div class="et-editor-wrap @error('body') is-invalid @enderror">
                            <div id="et-editor" data-et-editor>{!! $body !!}</div>
                        </div>
                        <input type="hidden" name="body" id="et-body" value="{{ $body }}" data-et-body>
                        @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <div class="form-text">
                            Tip: put <code>{button}</code> on its own line to choose where the button appears.
                            A quote block is shown as a green highlighted box.
                        </div>
                    </div>

                    <div>
                        <label for="button_text" class="form-label fw-semibold">Button label</label>
                        <input type="text" id="button_text" name="button_text" maxlength="60" required
                               value="{{ old('button_text', $content['button_text']) }}"
                               class="form-control @error('button_text') is-invalid @enderror" data-et-field>
                        @error('button_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">The button's link is set automatically (for example the verification link).</div>
                    </div>
                </div>
                <div class="ad-panel-foot d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary ad-btn-lift">
                        <i class="bi bi-check-lg me-1"></i>Save changes
                    </button>
                    <button type="submit" class="btn btn-light border" formaction="{{ route('admin.email-templates.test', $key) }}" formnovalidate>
                        <i class="bi bi-envelope-arrow-up me-1"></i>Send test to me
                    </button>
                    @if ($saved)
                        <button type="submit" form="et-reset-form" class="btn btn-outline-danger ms-sm-auto">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to default
                        </button>
                    @endif
                </div>
            </div>

            <div class="ad-panel h-auto db-in" style="--i: 3;">
                <div class="ad-panel-head">
                    <span class="ad-stat-icon ad-tone-purple"><i class="bi bi-braces"></i></span>
                    <div>
                        <div class="fw-semibold">Placeholders</div>
                        <div class="small text-muted">Click one to insert it where your cursor is. Replaced with real values when sent.</div>
                    </div>
                </div>
                <div class="p-3 p-md-4">
                    <div class="et-chips">
                        @foreach ($placeholders as $name => $description)
                            <button type="button" class="et-chip" data-et-placeholder="{{ '{'.$name.'}' }}" title="{{ $description }}">
                                <code>{{ '{'.$name.'}' }}</code>
                                <span>{{ $description }}</span>
                            </button>
                        @endforeach
                        <button type="button" class="et-chip" data-et-placeholder="{button}" title="Where the button goes">
                            <code>{button}</code>
                            <span>Where the button goes (own line)</span>
                        </button>
                    </div>
                    <div class="form-text mt-3">
                        To make a link, select some text, click the link icon and type a placeholder such as
                        <code>{billing_url}</code>, or a full address starting with <code>https://</code>.
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: live preview --}}
        <div class="col-xl-6">
            <div class="ad-panel h-auto ad-sticky db-in" style="--i: 4;">
                <div class="ad-panel-head justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ad-stat-icon ad-tone-green"><i class="bi bi-eye"></i></span>
                        <div>
                            <div class="fw-semibold">Live preview</div>
                            <div class="small text-muted">With sample values · updates as you type</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-light border" formaction="{{ route('admin.email-templates.preview', $key) }}" formtarget="et-preview" formnovalidate data-et-preview-button>
                        <i class="bi bi-arrow-clockwise"></i><span class="d-none d-sm-inline ms-1">Refresh</span>
                    </button>
                </div>
                <iframe name="et-preview" class="et-preview" title="Email preview" sandbox
                        src="{{ route('admin.email-templates.preview', $key) }}"></iframe>
            </div>
        </div>
    </div>
</form>

@if ($saved)
    <form method="POST" action="{{ route('admin.email-templates.reset', $key) }}" id="et-reset-form" class="d-none"
          onsubmit="return confirm('Reset this email to the built-in default? Your edited version will be deleted.');">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection
