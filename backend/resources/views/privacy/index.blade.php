@extends('layouts.app')

@section('title', 'Privacy Policy')

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

<div class="row justify-content-center">
    <div class="col-xl-11">

        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4 overflow-hidden">
            <div class="card-body p-4 p-md-5 bg-wa-light">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center fs-2 shadow-sm flex-shrink-0"
                          style="width: 72px; height: 72px;">
                        <i class="bi bi-shield-check"></i>
                    </span>
                    <div>
                        <h1 class="h2 mb-1">Privacy Policy</h1>
                        <p class="text-muted mb-2">
                            How {{ config('app.name') }} collects, uses and protects your information — in plain language.
                        </p>
                        <span class="badge rounded-pill text-bg-light border"><i class="bi bi-calendar3 me-1"></i>Last updated: {{ $lastUpdated->format('F j, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- At a glance --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-database text-primary fs-5"></i>
                            <h2 class="h6 mb-0">What we collect</h2>
                        </div>
                        <p class="small text-muted mb-0">
                            Your account details, the WhatsApp numbers you connect, and the messages sent and
                            received through them — only what's needed to run the service.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-hand-thumbs-down text-danger fs-5"></i>
                            <h2 class="h6 mb-0">What we never do</h2>
                        </div>
                        <p class="small text-muted mb-0">
                            We don't sell your data, show ads, or use tracking or analytics cookies. We never
                            store your card or UPI details.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-person-check text-success fs-5"></i>
                            <h2 class="h6 mb-0">You're in control</h2>
                        </div>
                        <p class="small text-muted mb-0">
                            Disconnect a number, revoke an API token, or delete your whole account yourself,
                            at any time — deletion removes your data from our systems.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Table of contents (sticky on large screens) --}}
            <div class="col-lg-3 d-none d-lg-block">
                <nav class="position-sticky" style="top: 1.5rem;" aria-label="On this page">
                    <div class="small text-uppercase text-muted fw-semibold mb-2">On this page</div>
                    <ol class="list-unstyled small mb-0">
                        @foreach ($sections as $id => $title)
                            <li class="mb-1">
                                <a href="#{{ $id }}" class="link-secondary text-decoration-none d-flex gap-2">
                                    <span class="text-muted" style="width: 1.25rem;">{{ $loop->iteration }}.</span>{{ $title }}
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            </div>

            {{-- Policy --}}
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5">

                        <section id="{{ $id = 'who-we-are' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>
                                {{ config('app.name') }} (the "Service") is operated by <strong>BriskBrain Technologies</strong>
                                ("we", "us", "our"), based in India. This policy explains what personal information we
                                handle when you visit our website, create an account, or use the Service, and the choices
                                you have. It should be read together with our <a href="{{ route('terms') }}">Terms of Service</a>.
                            </p>
                            <p class="mb-0">
                                {{ config('app.name') }} is an independent service and is <strong>not affiliated with WhatsApp or
                                Meta Platforms, Inc.</strong> WhatsApp's own privacy policy applies separately to your use of WhatsApp.
                            </p>
                        </section>

                        <section id="{{ $id = 'what-we-collect' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>We only collect what we need to run the Service:</p>
                            <div class="table-responsive">
                                <table class="table table-sm align-top small">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 28%;">Category</th>
                                            <th>What exactly</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><i class="bi bi-person me-1 text-primary"></i>Account</td>
                                            <td>Your name, email address, and password. Your password is stored only as a secure one-way hash — we can never see it.</td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-phone me-1 text-primary"></i>Connected WhatsApp numbers</td>
                                            <td>The name you give each instance, the connected phone number, its connection status, and the technical session keys needed to keep it connected.</td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-chat-left-text me-1 text-primary"></i>Messages</td>
                                            <td>The text of messages you send or receive through the Service, the sender/recipient phone numbers, delivery status, and timestamps.</td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-key me-1 text-primary"></i>API & webhooks</td>
                                            <td>API tokens (stored only as a secure hash plus their first few characters, so you can recognise them), the webhook URL you set, and a log of webhook deliveries (time, status, error — not the message content).</td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-credit-card me-1 text-primary"></i>Billing</td>
                                            <td>Your plan, subscription status, renewal date, and the subscription reference from our payment processor. <strong>We never receive or store your card, UPI, or bank details</strong> — Cashfree handles those directly.</td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-pc-display me-1 text-primary"></i>Technical</td>
                                            <td>Your IP address and browser type while you're logged in (to keep your session secure), and basic server error logs.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mb-0 small text-muted">
                                <i class="bi bi-info-circle me-1"></i>We don't use analytics tools, advertising networks, or tracking pixels.
                            </p>
                        </section>

                        <section id="{{ $id = 'how-we-use' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>We use your information only to:</p>
                            <ul>
                                <li>create and secure your account, and verify your email address;</li>
                                <li>connect your WhatsApp number and send or receive messages when you (or your API calls) ask us to;</li>
                                <li>forward incoming messages to the webhook URL you configured;</li>
                                <li>show you your message history, API logs, and usage against your plan's limits;</li>
                                <li>process your subscription and payments;</li>
                                <li>send you service emails — email verification, password resets, and important account notices;</li>
                                <li>prevent abuse (for example rate limiting, and suspending accounts that break our Terms), and fix problems;</li>
                                <li>meet our legal obligations.</li>
                            </ul>
                            <p class="mb-0">
                                We do <strong>not</strong> read your messages for marketing, use them to train AI models, or send you
                                marketing emails without your consent.
                            </p>
                        </section>

                        <section id="{{ $id = 'whatsapp-data' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>
                                When you scan the QR code, WhatsApp creates a linked-device session for our server — the same way
                                WhatsApp Web works. We store that session's keys on our servers, <strong>outside the public web
                                directory</strong>, one isolated folder per instance, so the connection survives restarts.
                            </p>
                            <ul class="mb-0">
                                <li>Each instance's session and messages are only accessible to the account that owns it.</li>
                                <li>You can disconnect at any time from the instance page, or remove the linked device from WhatsApp on your phone (<em>Settings → Linked devices</em>).</li>
                                <li>Our staff only access message content when needed to investigate a problem you reported, or when required by law.</li>
                            </ul>
                        </section>

                        <section id="{{ $id = 'sharing' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p><strong>We don't sell or rent your personal information.</strong> We share it only with:</p>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="fw-semibold small mb-1"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp</div>
                                        <div class="small text-muted">Messages you send go to WhatsApp for delivery, as with any WhatsApp message.</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="fw-semibold small mb-1"><i class="bi bi-credit-card me-1 text-primary"></i>Cashfree Payments</div>
                                        <div class="small text-muted">Our payment processor, for paid subscriptions.</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="fw-semibold small mb-1"><i class="bi bi-envelope me-1 text-primary"></i>Email provider</div>
                                        <div class="small text-muted">Used to deliver verification, password-reset and account emails (currently Google).</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="fw-semibold small mb-1"><i class="bi bi-hdd-network me-1 text-primary"></i>Hosting providers</div>
                                        <div class="small text-muted">The servers that run the Service and store its data.</div>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                We also send data to <strong>your own webhook URL</strong> when you configure one, and may disclose
                                information if required by law, to protect our rights or users' safety, or as part of a merger or sale
                                of our business (in which case this policy would continue to apply).
                            </p>
                        </section>

                        <section id="{{ $id = 'cookies' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>We use only <strong>strictly necessary</strong> cookies:</p>
                            <ul>
                                <li><strong>Session cookie</strong> — keeps you logged in.</li>
                                <li><strong>Security (CSRF) cookie</strong> — protects your forms from being submitted by other websites.</li>
                                <li><strong>"Remember me" cookie</strong> — only if you tick that box when logging in.</li>
                            </ul>
                            <p class="mb-0">No advertising, analytics or third-party tracking cookies. Because these cookies are essential, the Service doesn't work without them.</p>
                        </section>

                        <section id="{{ $id = 'retention' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <ul>
                                <li><strong>While your account is open</strong>, we keep your data so the Service keeps working, including your message history.</li>
                                <li><strong>When you delete your account</strong>, we delete your account, instances, WhatsApp session data, API tokens, messages, webhook logs and subscription record.</li>
                                <li><strong>Some records are kept longer</strong> where needed: records of administrative actions on an account (which include its name and email) are kept for security and accountability, and payment records are kept by Cashfree and by us as tax and accounting law requires.</li>
                                <li>Server error logs are used only to diagnose and fix problems, and are cleared periodically.</li>
                            </ul>
                        </section>

                        <section id="{{ $id = 'security' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <div class="row g-2 small mb-3">
                                @foreach ([
                                    ['bi-lock', 'Passwords stored as one-way hashes'],
                                    ['bi-key', 'API tokens stored hashed, shown only once, revocable'],
                                    ['bi-people', 'Strict separation between customer accounts'],
                                    ['bi-shield-lock', 'Signed webhooks and protected internal services'],
                                    ['bi-speedometer2', 'Rate limiting and login lockout against abuse'],
                                    ['bi-envelope-check', 'Email verification for new accounts'],
                                ] as [$icon, $text])
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi {{ $icon }} text-success mt-1"></i><span>{{ $text }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mb-0">
                                No system is 100% secure, so please keep your password and API tokens safe. If we become aware of a
                                breach affecting your personal data, we'll notify you and the relevant authorities as the law requires.
                            </p>
                        </section>

                        <section id="{{ $id = 'your-rights' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>
                                Under applicable law, including India's <em>Digital Personal Data Protection Act, 2023</em>, you can:
                            </p>
                            <ul>
                                <li><strong>Access</strong> the personal data we hold about you;</li>
                                <li><strong>Correct</strong> inaccurate or incomplete data;</li>
                                <li><strong>Delete</strong> your data — you can delete your account yourself from the <em>Account</em> page;</li>
                                <li><strong>Withdraw consent</strong> and stop using the Service at any time;</li>
                                <li><strong>Raise a grievance</strong> with us, and if unresolved, with the Data Protection Board of India;</li>
                                <li><strong>Nominate</strong> someone to exercise these rights on your behalf in case of death or incapacity.</li>
                            </ul>
                            <p class="mb-0">To use any of these rights, email us (see <a href="#contact">Contact us</a>). We'll respond within 30 days.</p>
                        </section>

                        <section id="{{ $id = 'your-customers' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p class="mb-0">
                                When you message your own customers or contacts through the Service, <strong>you</strong> decide what to
                                send and to whom, and we process their phone numbers and messages on your behalf. You're responsible for
                                having their permission to contact them on WhatsApp and for handling their data lawfully — including
                                on any webhook endpoint you connect.
                            </p>
                        </section>

                        <section id="{{ $id = 'children' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p class="mb-0">
                                The Service is for businesses and adults. You must be at least 18 years old to create an account, and we
                                don't knowingly collect data from children. If you believe a child has given us personal data, contact us
                                and we'll delete it.
                            </p>
                        </section>

                        <section id="{{ $id = 'changes' }}" class="mb-5">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p class="mb-0">
                                We may update this policy as the Service changes. We'll update the "Last updated" date at the top, and for
                                significant changes we'll also notify you by email or in the dashboard before they take effect.
                            </p>
                        </section>

                        <section id="{{ $id = 'contact' }}">
                            <h2 class="h5"><span class="text-primary">{{ ++$n }}.</span> {{ $sections[$id] }}</h2>
                            <p>Questions, requests, or a privacy complaint? Our grievance contact is:</p>
                            <div class="border rounded p-3 bg-body-tertiary d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                                <span class="rounded-circle bg-wa-light text-primary d-inline-flex align-items-center justify-content-center fs-4 flex-shrink-0"
                                      style="width: 48px; height: 48px;">
                                    <i class="bi bi-envelope-paper"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold">BriskBrain Technologies — Privacy &amp; Grievances</div>
                                    <a href="mailto:briskbraintechnologies@gmail.com">briskbraintechnologies@gmail.com</a>
                                    <div class="small">or use our <a href="{{ route('contact', ['topic' => 'privacy']) }}">contact form</a> (topic: Privacy / data request)</div>
                                </div>
                            </div>
                        </section>

                    </div>
                </div>

                <p class="text-center text-muted small mt-3 mb-0">
                    See also our <a href="{{ route('terms') }}">Terms of Service</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
