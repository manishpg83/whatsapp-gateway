@extends('layouts.app')

@section('title', 'Contact us')

@section('content')
@php
    $topicIcons = [
        'general' => 'bi-chat-dots',
        'sales' => 'bi-tags',
        'technical' => 'bi-tools',
        'billing' => 'bi-receipt',
        'privacy' => 'bi-shield-lock',
    ];
    $currentTopic = old('topic', $selectedTopic);
@endphp

<div class="tm-page">
    {{-- Header --}}
    <div class="db-hero db-in tm-hero rounded-4 p-4 p-md-5 mb-4">
        <span class="db-orb db-orb-1" aria-hidden="true"></span>
        <span class="db-orb db-orb-2" aria-hidden="true"></span>

        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 gap-md-4">
            <span class="tm-hero-icon"><i class="bi bi-headset"></i></span>
            <div>
                <span class="ad-eyebrow"><i class="bi bi-life-preserver"></i> Support</span>
                <h1 class="h2 mt-2 mb-1">Contact us</h1>
                <p class="text-muted mb-3">
                    Questions, a problem with an instance, or something about billing? Send us a message — a real person will reply.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="ad-chip"><i class="bi bi-clock"></i>Reply within 1 business day</span>
                    <span class="ad-chip"><i class="bi bi-calendar-week"></i>Mon–Sat, IST</span>
                    <span class="ad-chip"><i class="bi bi-person-check"></i>Answered by a real person</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- The form --}}
        <div class="col-lg-8">
            <div class="ad-panel h-auto db-in" style="--i: 1;">
                <div class="ad-panel-head">
                    <span class="ad-stat-icon ad-tone-green"><i class="bi bi-envelope-paper"></i></span>
                    <div>
                        <div class="fw-semibold">Send us a message</div>
                        <div class="text-muted small">We'll reply to the email address you enter below.</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('contact.send') }}" novalidate class="p-3 p-md-4">
                    @csrf

                    {{-- Honeypot: hidden from people, only bots fill it in. --}}
                    <div class="d-none" aria-hidden="true">
                        <label for="website">Leave this empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    {{-- Topic cards (radio buttons) --}}
                    <fieldset class="mb-4">
                        <legend class="form-label fs-6 mb-2">What's it about?</legend>
                        <div class="ct-topics">
                            @foreach ($topics as $value => $label)
                                <label class="ct-topic">
                                    <input type="radio" name="topic" value="{{ $value }}" class="ct-topic-input" required @checked($currentTopic === $value)>
                                    <span class="ct-topic-card">
                                        <i class="bi {{ $topicIcons[$value] ?? 'bi-chat' }}"></i>
                                        <span>{{ $label }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('topic')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </fieldset>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Your name</label>
                            <div class="ct-field">
                                <i class="bi bi-person"></i>
                                <input type="text" id="name" name="name" value="{{ old('name', auth()->user()?->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" required maxlength="100" autocomplete="name">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <div class="ct-field">
                                <i class="bi bi-envelope"></i>
                                <input type="email" id="email" name="email" value="{{ old('email', auth()->user()?->email) }}"
                                       class="form-control @error('email') is-invalid @enderror" required maxlength="255" autocomplete="email">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-end">
                                <label for="message" class="form-label">Message</label>
                                <span class="small text-muted mb-2" data-char-count>{{ mb_strlen(old('message', '')) }} / 5000</span>
                            </div>
                            <textarea id="message" name="message" rows="7" maxlength="5000"
                                      class="form-control ct-textarea @error('message') is-invalid @enderror" required
                                      placeholder="Tell us what's going on. For an instance problem, include the instance name.">{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="tm-callout tm-callout-amber mt-3 mb-0 py-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div class="small">Never include your password or API access token.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg ad-btn-lift mt-4 px-4 ct-send">
                        <i class="bi bi-send me-2"></i>Send message
                    </button>
                </form>
            </div>
        </div>

        {{-- Side: other ways + quick help --}}
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-3">
                <div class="ct-card ct-card-feature db-in" style="--i: 2;">
                    <span class="ad-stat-icon"><i class="bi bi-envelope-at"></i></span>
                    <div style="min-width: 0;">
                        <div class="ct-card-label">Email us directly</div>
                        {{-- <wbr> lets a long address wrap after the @ instead of mid-word. --}}
                        <a href="mailto:{{ $supportEmail }}" class="ct-email">{{ Str::before($supportEmail, '@') }}@<wbr>{{ Str::after($supportEmail, '@') }}</a>
                    </div>
                </div>

                <div class="ct-card db-in" style="--i: 3;">
                    <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <div class="fw-semibold">Response time</div>
                        <div class="small text-muted">We usually reply within 1 business day (Mon–Sat, IST).</div>
                    </div>
                </div>

                <div class="ad-panel h-auto db-in" style="--i: 4;">
                    <div class="ad-panel-head">
                        <span class="ad-stat-icon ad-tone-amber"><i class="bi bi-lightbulb"></i></span>
                        <div class="fw-semibold">Quick help</div>
                    </div>
                    <ul class="ct-help">
                        <li>
                            <i class="bi bi-qr-code"></i>
                            <span><strong>QR code not working?</strong> Open your instance and click <em>Reconnect</em> to get a fresh one.</span>
                        </li>
                        <li>
                            <i class="bi bi-code-slash"></i>
                            <span><strong>API errors?</strong> Check the
                                @auth
                                    <a href="{{ route('docs.index') }}">API Docs</a> and your <a href="{{ route('api-logs.index') }}">API Logs</a>.
                                @else
                                    API Docs after logging in.
                                @endauth
                            </span>
                        </li>
                        <li>
                            <i class="bi bi-key"></i>
                            <span><strong>Forgot your password?</strong> <a href="{{ route('password.request') }}">Reset it here</a>.</span>
                        </li>
                        <li>
                            <i class="bi bi-shield-check"></i>
                            <span><strong>Your data:</strong> see our <a href="{{ route('privacy') }}">Privacy Policy</a> and <a href="{{ route('terms') }}">Terms</a>.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live character count for the message box.
(function () {
    const box = document.getElementById('message');
    const count = document.querySelector('[data-char-count]');
    box.addEventListener('input', () => {
        count.textContent = `${box.value.length} / 5000`;
    });
})();
</script>
@endsection
