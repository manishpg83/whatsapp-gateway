@extends('layouts.app')

@section('title', 'Contact us')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-11">

        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4 overflow-hidden">
            <div class="card-body p-4 p-md-5 bg-wa-light">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center fs-2 shadow-sm flex-shrink-0"
                          style="width: 72px; height: 72px;">
                        <i class="bi bi-headset"></i>
                    </span>
                    <div>
                        <h1 class="h2 mb-1">Contact us</h1>
                        <p class="text-muted mb-0">
                            Questions, a problem with an instance, or something about billing? Send us a message — a real person will reply.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Left: other ways + quick help --}}
            <div class="col-lg-4 order-2 order-lg-1">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <span class="rounded-circle bg-wa-light text-primary d-inline-flex align-items-center justify-content-center fs-5 flex-shrink-0"
                                  style="width: 44px; height: 44px;">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <div>
                                <div class="fw-semibold">Email us directly</div>
                                <a href="mailto:{{ $supportEmail }}" class="small text-break">{{ $supportEmail }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center fs-5 flex-shrink-0"
                                  style="width: 44px; height: 44px; background-color: var(--wa-info-light); color: var(--wa-info);">
                                <i class="bi bi-clock"></i>
                            </span>
                            <div>
                                <div class="fw-semibold">Response time</div>
                                <div class="small text-muted">We usually reply within 1 business day (Mon–Sat, IST).</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="fw-semibold mb-2"><i class="bi bi-lightbulb me-1 text-warning"></i>Quick help</div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-2 d-flex gap-2">
                                <i class="bi bi-qr-code text-muted mt-1"></i>
                                <span><strong>QR code not working?</strong> Open your instance and click <em>Reconnect</em> to get a fresh one.</span>
                            </li>
                            <li class="mb-2 d-flex gap-2">
                                <i class="bi bi-code-slash text-muted mt-1"></i>
                                <span><strong>API errors?</strong> Check the
                                    @auth
                                        <a href="{{ route('docs.index') }}">API Docs</a> and your <a href="{{ route('api-logs.index') }}">API Logs</a>.
                                    @else
                                        API Docs after logging in.
                                    @endauth
                                </span>
                            </li>
                            <li class="mb-2 d-flex gap-2">
                                <i class="bi bi-key text-muted mt-1"></i>
                                <span><strong>Forgot your password?</strong> <a href="{{ route('password.request') }}">Reset it here</a>.</span>
                            </li>
                            <li class="d-flex gap-2">
                                <i class="bi bi-shield-check text-muted mt-1"></i>
                                <span><strong>Your data:</strong> see our <a href="{{ route('privacy') }}">Privacy Policy</a> and <a href="{{ route('terms') }}">Terms</a>.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Right: the form --}}
            <div class="col-lg-8 order-1 order-lg-2">
                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h5 mb-1">Send us a message</h2>
                        <p class="text-muted small mb-4">We'll reply to the email address you enter below.</p>

                        <form method="POST" action="{{ route('contact.send') }}" novalidate>
                            @csrf

                            {{-- Honeypot: hidden from people, only bots fill it in. --}}
                            <div class="d-none" aria-hidden="true">
                                <label for="website">Leave this empty</label>
                                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Your name</label>
                                    <input type="text" id="name" name="name" value="{{ old('name', auth()->user()?->name) }}"
                                           class="form-control @error('name') is-invalid @enderror" required maxlength="100" autocomplete="name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email" value="{{ old('email', auth()->user()?->email) }}"
                                           class="form-control @error('email') is-invalid @enderror" required maxlength="255" autocomplete="email">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="topic" class="form-label">Topic</label>
                                    <select id="topic" name="topic" class="form-select @error('topic') is-invalid @enderror" required>
                                        <option value="" disabled @selected(! old('topic', $selectedTopic))>Choose a topic…</option>
                                        @foreach ($topics as $value => $label)
                                            <option value="{{ $value }}" @selected(old('topic', $selectedTopic) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('topic')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea id="message" name="message" rows="6" maxlength="5000"
                                              class="form-control @error('message') is-invalid @enderror" required
                                              placeholder="Tell us what's going on. For an instance problem, include the instance name.">{{ old('message') }}</textarea>
                                    @error('message')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Never include your password or API access token.
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4 px-4">
                                <i class="bi bi-send me-1"></i>Send message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
