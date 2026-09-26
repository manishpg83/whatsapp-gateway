@extends('layouts.app')

@section('title', 'Privacy Policy')
@section('robots', 'index, follow')
@section('meta_description', "How WhatsApp Gateway collects, uses and protects your data — WhatsApp sessions, messages, cookies, retention and your rights under India's DPDP Act, 2023.")

@push('structured_data')
@include('partials.breadcrumb-schema', ['crumb' => 'Privacy Policy'])
@endpush

@section('content')
@php
    $sections = [
        'who-we-are' => 'Who we are',
        'what-we-collect' => 'What we collect',
        'how-we-use' => 'How we use it',
        'whatsapp-data' => 'WhatsApp sessions & messages',
        'sharing' => 'Who we share it with',
        'cookies' => 'Cookies',
        'retention' => 'How long we keep it',
        'security' => 'How we protect it',
        'your-rights' => 'Your rights',
        'your-customers' => 'Your own customers\' data',
        'children' => 'Children',
        'changes' => 'Changes to this policy',
        'contact' => 'Contact us',
    ];
    $n = 0;
@endphp

<div class="tm-page">
    {{-- Header --}}
    <div class="db-hero db-in tm-hero rounded-4 p-4 p-md-5 mb-4">
        <span class="db-orb db-orb-1" aria-hidden="true"></span>
        <span class="db-orb db-orb-2" aria-hidden="true"></span>

        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 gap-md-4">
            <span class="tm-hero-icon"><i class="bi bi-shield-check"></i></span>
            <div>
                <span class="ad-eyebrow"><i class="bi bi-bank"></i> Legal</span>
                <h1 class="h2 mt-2 mb-1">Privacy Policy</h1>
                <p class="text-muted mb-3">
                    How {{ config('app.name') }} collects, uses and protects your information — in plain language.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="ad-chip"><i class="bi bi-calendar3"></i>Last updated: {{ $lastUpdated->format('F j, Y') }}</span>
                    <span class="ad-chip"><i class="bi bi-list-ol"></i>{{ count($sections) }} sections</span>
                    <span class="ad-chip"><i class="bi bi-geo-alt"></i>DPDP Act, 2023 (India)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- At a glance --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="#what-we-collect" class="tm-key db-in" style="--i: 1;">
                <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-database"></i></span>
                <span>
                    <span class="tm-key-title">What we collect</span>
                    <span class="tm-key-text">
                        Your account details, the WhatsApp numbers you connect, and the messages sent and
                        received through them — only what's needed to run the service.
                    </span>
                </span>
            </a>
        </div>
        <div class="col-md-4">
            <a href="#sharing" class="tm-key db-in" style="--i: 2;">
                <span class="ad-stat-icon ad-tone-red"><i class="bi bi-hand-thumbs-down"></i></span>
                <span>
                    <span class="tm-key-title">What we never do</span>
                    <span class="tm-key-text">
                        We don't sell your data, show ads, or use tracking or analytics cookies. We never
                        store your card or UPI details.
                    </span>
                </span>
            </a>
        </div>
        <div class="col-md-4">
            <a href="#your-rights" class="tm-key db-in" style="--i: 3;">
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-person-check"></i></span>
                <span>
                    <span class="tm-key-title">You're in control</span>
                    <span class="tm-key-text">
                        Disconnect a number, revoke an API token, or delete your whole account yourself,
                        at any time — deletion removes your data from our systems.
                    </span>
                </span>
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Table of contents: sticky sidebar on large screens, a collapsible list below that --}}
        <div class="col-lg-4 col-xl-3">
            <nav class="tm-toc db-in" style="--i: 4;" aria-label="On this page">
                <details class="tm-toc-details" data-toc-details>
                    <summary class="tm-toc-title">
                        <span><i class="bi bi-list-ul me-2"></i>On this page</span>
                        <i class="bi bi-chevron-down tm-toc-chevron d-lg-none"></i>
                    </summary>
                    <ol class="tm-toc-list">
                        @foreach ($sections as $id => $title)
                            <li>
                                <a href="#{{ $id }}" data-toc-link="{{ $id }}">
                                    <span class="tm-toc-num">{{ $loop->iteration }}</span>
                                    <span>{{ $title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </details>
            </nav>
        </div>

        {{-- The policy --}}
        <div class="col-lg-8 col-xl-9">
            <div class="ad-panel h-auto tm-body db-in" style="--i: 5;">

                <section id="{{ $id = 'who-we-are' }}" class="tm-section pt-0">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>
                        {{ config('app.name') }} (the "Service") is operated by <strong>BriskBrain Technologies</strong>
                        ("we", "us", "our"), based in India. This policy explains what personal information we
                        handle when you visit our website, create an account, or use the Service, and the choices
                        you have. It should be read together with our <a href="{{ route('terms') }}">Terms of Service</a>.
                    </p>
                    <div class="tm-callout tm-callout-amber">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            {{ config('app.name') }} is an independent service and is <strong>not affiliated with WhatsApp or
                            Meta Platforms, Inc.</strong> WhatsApp's own privacy policy applies separately to your use of WhatsApp.
                        </div>
                    </div>
                </section>

                <section id="{{ $id = 'what-we-collect' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>We only collect what we need to run the Service:</p>
                    <div class="tm-data">
                        @foreach ([
                            ['bi-person', 'green', 'Account', 'Your name, email address, and password. Your password is stored only as a secure one-way hash — we can never see it.'],
                            ['bi-phone', 'blue', 'Connected WhatsApp numbers', 'The name you give each instance, the connected phone number, its connection status, and the technical session keys needed to keep it connected.'],
                            ['bi-chat-left-text', 'purple', 'Messages', 'The text of messages you send or receive through the Service, media files you receive (photos, videos, voice notes, documents, stickers), the sender/recipient phone numbers, delivery status, and timestamps. View-once photos and videos are never downloaded.'],
                            ['bi-key', 'amber', 'API & webhooks', 'API tokens (stored only as a secure hash plus their first few characters, so you can recognise them), the webhook URL you set, and a log of webhook deliveries (time, status, error — not the message content).'],
                            ['bi-credit-card', 'green', 'Billing', null],
                            ['bi-pc-display', 'grey', 'Technical', 'Your IP address and browser type while you\'re logged in (to keep your session secure), and basic server error logs.'],
                        ] as [$icon, $tone, $category, $text])
                            <div class="tm-data-row">
                                <div class="tm-data-cat">
                                    <span class="ad-stat-icon ad-avatar-sm ad-tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                                    {{ $category }}
                                </div>
                                <div class="tm-data-text">
                                    @if ($text)
                                        {{ $text }}
                                    @else
                                        Your plan, subscription status, renewal date, and the subscription reference from our payment processor.
                                        <strong>We never receive or store your card, UPI, or bank details</strong> — Cashfree handles those directly.
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="tm-tip">
                        <i class="bi bi-info-circle"></i>
                        <div>We don't use analytics tools, advertising networks, or tracking pixels.</div>
                    </div>
                </section>

                <section id="{{ $id = 'how-we-use' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>We use your information only to:</p>
                    <ul class="tm-list">
                        <li>create and secure your account, and verify your email address;</li>
                        <li>connect your WhatsApp number and send or receive messages when you (or your API calls) ask us to;</li>
                        <li>forward incoming messages to the webhook URL you configured;</li>
                        <li>show you your message history, API logs, and usage against your plan's limits;</li>
                        <li>process your subscription and payments;</li>
                        <li>send you service emails — email verification, password resets, and important account notices;</li>
                        <li>prevent abuse (for example rate limiting, and suspending accounts that break our Terms), and fix problems;</li>
                        <li>meet our legal obligations.</li>
                    </ul>
                    <div class="tm-callout tm-callout-green">
                        <i class="bi bi-shield-fill-check"></i>
                        <div>
                            We do <strong>not</strong> read your messages for marketing, use them to train AI models, or send you
                            marketing emails without your consent.
                        </div>
                    </div>
                </section>

                <section id="{{ $id = 'whatsapp-data' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>
                        When you scan the QR code, WhatsApp creates a linked-device session for our server — the same way
                        WhatsApp Web works. We store that session's keys on our servers, <strong>outside the public web
                        directory</strong>, one isolated folder per instance, so the connection survives restarts.
                    </p>
                    <ul class="tm-list mb-0">
                        <li>Each instance's session and messages are only accessible to the account that owns it.</li>
                        <li>You can disconnect at any time from the instance page, or remove the linked device from WhatsApp on your phone (<em>Settings → Linked devices</em>).</li>
                        <li>Our staff only access message content when needed to investigate a problem you reported, or when required by law.</li>
                    </ul>
                </section>

                <section id="{{ $id = 'sharing' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p><strong>We don't sell or rent your personal information.</strong> We share it only with:</p>
                    <div class="row g-3 mb-3">
                        @foreach ([
                            ['bi-whatsapp', 'green', 'WhatsApp', 'Messages you send go to WhatsApp for delivery, as with any WhatsApp message.'],
                            ['bi-credit-card', 'blue', 'Cashfree Payments', 'Our payment processor, for paid subscriptions.'],
                            ['bi-envelope', 'purple', 'Email provider', 'Used to deliver verification, password-reset and account emails (currently Google).'],
                            ['bi-hdd-network', 'amber', 'Hosting providers', 'The servers that run the Service and store its data.'],
                        ] as [$icon, $tone, $who, $why])
                            <div class="col-sm-6">
                                <div class="tm-party">
                                    <span class="ad-stat-icon ad-avatar-sm ad-tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                                    <div>
                                        <div class="fw-semibold">{{ $who }}</div>
                                        <div class="small text-muted">{{ $why }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mb-0">
                        We also send data to <strong>your own webhook URL</strong> when you configure one, and may disclose
                        information if required by law, to protect our rights or users' safety, or as part of a merger or sale
                        of our business (in which case this policy would continue to apply).
                    </p>
                </section>

                <section id="{{ $id = 'cookies' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>We use only <strong>strictly necessary</strong> cookies:</p>
                    <ul class="tm-list">
                        <li><strong>Session cookie</strong> — keeps you logged in.</li>
                        <li><strong>Security (CSRF) cookie</strong> — protects your forms from being submitted by other websites.</li>
                        <li><strong>"Remember me" cookie</strong> — only if you tick that box when logging in.</li>
                    </ul>
                    <p class="mb-0">No advertising, analytics or third-party tracking cookies. Because these cookies are essential, the Service doesn't work without them.</p>
                </section>

                <section id="{{ $id = 'retention' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <ul class="tm-list mb-0">
                        <li><strong>While your account is open</strong>, we keep your data so the Service keeps working, including your message history.</li>
                        <li><strong>When you delete your account</strong>, we delete your account, instances, WhatsApp session data, API tokens, messages, webhook logs and subscription record.</li>
                        <li><strong>Some records are kept longer</strong> where needed: records of administrative actions on an account (which include its name and email) are kept for security and accountability, and payment records are kept by Cashfree and by us as tax and accounting law requires.</li>
                        <li>Server error logs are used only to diagnose and fix problems, and are cleared periodically.</li>
                    </ul>
                </section>

                <section id="{{ $id = 'security' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <div class="row g-2 mb-3">
                        @foreach ([
                            ['bi-lock', 'Passwords stored as one-way hashes'],
                            ['bi-key', 'API tokens stored hashed, shown only once, revocable'],
                            ['bi-people', 'Strict separation between customer accounts'],
                            ['bi-shield-lock', 'Signed webhooks and protected internal services'],
                            ['bi-speedometer2', 'Rate limiting and login lockout against abuse'],
                            ['bi-envelope-check', 'Email verification for new accounts'],
                        ] as [$icon, $text])
                            <div class="col-sm-6">
                                <div class="tm-safeguard">
                                    <i class="bi {{ $icon }}"></i><span>{{ $text }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mb-0">
                        No system is 100% secure, so please keep your password and API tokens safe. If we become aware of a
                        breach affecting your personal data, we'll notify you and the relevant authorities as the law requires.
                    </p>
                </section>

                <section id="{{ $id = 'your-rights' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>
                        Under applicable law, including India's <em>Digital Personal Data Protection Act, 2023</em>, you can:
                    </p>
                    <ul class="tm-list">
                        <li><strong>Access</strong> the personal data we hold about you;</li>
                        <li><strong>Correct</strong> inaccurate or incomplete data;</li>
                        <li><strong>Delete</strong> your data — you can delete your account yourself from the <em>Account</em> page;</li>
                        <li><strong>Withdraw consent</strong> and stop using the Service at any time;</li>
                        <li><strong>Raise a grievance</strong> with us, and if unresolved, with the Data Protection Board of India;</li>
                        <li><strong>Nominate</strong> someone to exercise these rights on your behalf in case of death or incapacity.</li>
                    </ul>
                    <p class="mb-0">To use any of these rights, email us (see <a href="#contact">Contact us</a>). We'll respond within 30 days.</p>
                </section>

                <section id="{{ $id = 'your-customers' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        When you message your own customers or contacts through the Service, <strong>you</strong> decide what to
                        send and to whom, and we process their phone numbers and messages on your behalf. You're responsible for
                        having their permission to contact them on WhatsApp and for handling their data lawfully — including
                        on any webhook endpoint you connect.
                    </p>
                </section>

                <section id="{{ $id = 'children' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        The Service is for businesses and adults. You must be at least 18 years old to create an account, and we
                        don't knowingly collect data from children. If you believe a child has given us personal data, contact us
                        and we'll delete it.
                    </p>
                </section>

                <section id="{{ $id = 'changes' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        We may update this policy as the Service changes. We'll update the "Last updated" date at the top, and for
                        significant changes we'll also notify you by email or in the dashboard before they take effect.
                    </p>
                </section>

                <section id="{{ $id = 'contact' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>Questions, requests, or a privacy complaint? Our grievance contact is:</p>
                    <div class="tm-contact">
                        <span class="ad-stat-icon ad-tone-green"><i class="bi bi-envelope-paper"></i></span>
                        <div style="min-width: 0;">
                            <div class="fw-semibold">BriskBrain Technologies — Privacy &amp; Grievances</div>
                            <a href="mailto:briskbraintechnologies@gmail.com" class="text-break">briskbraintechnologies@gmail.com</a>
                            <div class="small text-muted">or use our <a href="{{ route('contact', ['topic' => 'privacy']) }}">contact form</a> (topic: Privacy / data request)</div>
                        </div>
                    </div>
                </section>
            </div>

            <p class="text-center text-muted small mt-3 mb-0">
                See also our <a href="{{ route('terms') }}">Terms of Service</a>.
            </p>
        </div>
    </div>
</div>
@endsection
