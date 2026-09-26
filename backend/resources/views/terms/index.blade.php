@extends('layouts.app')

@section('title', 'Terms of Service')
@section('robots', 'index, follow')
@section('meta_description', "Read WhatsApp Gateway's Terms of Service: acceptable use, bulk messaging and blocked-number responsibility, subscriptions and cancellation, and liability.")

@push('structured_data')
@include('partials.breadcrumb-schema', ['crumb' => 'Terms of Service'])
@endpush

@section('content')
@php
    // id => title. Section 4's id is linked from the API docs, keep it.
    $sections = [
        'what-it-is' => 'What the Service is',
        'account' => 'Your account',
        'acceptable-use' => 'Acceptable use',
        'bulk-messaging' => 'Bulk messaging and blocked numbers — your responsibility',
        'billing' => 'Subscriptions, billing, and cancellation',
        'content' => 'Your content and data',
        'availability' => 'Service availability',
        'termination' => 'Termination',
        'liability' => 'Disclaimers and limitation of liability',
        'changes' => 'Changes to these Terms',
        'law' => 'Governing law',
        'contact' => 'Contact',
    ];
    $n = 0;
@endphp

<div class="tm-page">
    {{-- Header --}}
    <div class="db-hero db-in tm-hero rounded-4 p-4 p-md-5 mb-4">
        <span class="db-orb db-orb-1" aria-hidden="true"></span>
        <span class="db-orb db-orb-2" aria-hidden="true"></span>

        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 gap-md-4">
            <span class="tm-hero-icon"><i class="bi bi-file-earmark-text"></i></span>
            <div>
                <span class="ad-eyebrow"><i class="bi bi-bank"></i> Legal</span>
                <h1 class="h2 mt-2 mb-1">Terms of Service</h1>
                <p class="text-muted mb-3">The rules for using {{ config('app.name') }} — please read them before connecting a number.</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="ad-chip"><i class="bi bi-calendar3"></i>Last updated: {{ $lastUpdated->format('F j, Y') }}</span>
                    <span class="ad-chip"><i class="bi bi-list-ol"></i>{{ count($sections) }} sections</span>
                    <span class="ad-chip"><i class="bi bi-geo-alt"></i>Governed by Indian law</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Key points --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="#bulk-messaging" class="tm-key db-in" style="--i: 1;">
                <span class="ad-stat-icon ad-tone-red"><i class="bi bi-exclamation-octagon"></i></span>
                <span>
                    <span class="tm-key-title">Your number, your responsibility</span>
                    <span class="tm-key-text">If WhatsApp restricts or bans a number, we can't prevent or undo it.</span>
                </span>
            </a>
        </div>
        <div class="col-md-4">
            <a href="#what-it-is" class="tm-key db-in" style="--i: 2;">
                <span class="ad-stat-icon ad-tone-amber"><i class="bi bi-info-circle"></i></span>
                <span>
                    <span class="tm-key-title">Not WhatsApp or Meta</span>
                    <span class="tm-key-text">An independent service using a WhatsApp Web-style session.</span>
                </span>
            </a>
        </div>
        <div class="col-md-4">
            <a href="#billing" class="tm-key db-in" style="--i: 3;">
                <span class="ad-stat-icon ad-tone-green"><i class="bi bi-arrow-repeat"></i></span>
                <span>
                    <span class="tm-key-title">Cancel any time</span>
                    <span class="tm-key-text">Stop future billing from your Billing page whenever you like.</span>
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
                                    <span>{{ Str::before($title, ' — ') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </details>
            </nav>
        </div>

        {{-- The Terms --}}
        <div class="col-lg-8 col-xl-9">
            <div class="ad-panel h-auto tm-body db-in" style="--i: 5;">
                <p class="tm-intro">
                    These Terms of Service ("Terms") govern your access to and use of
                    {{ config('app.name') }} (the "Service"), operated by BriskBrain Technologies
                    ("we", "us", "our"). By creating an account or using the Service, you agree
                    to be bound by these Terms. If you do not agree, do not use the Service.
                </p>

                <section id="{{ $id = 'what-it-is' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>
                        {{ config('app.name') }} lets you connect a WhatsApp number to your own
                        account and send and receive WhatsApp messages through a REST API, using
                        a WhatsApp Web-style device session.
                    </p>
                    <div class="tm-callout tm-callout-amber">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Important:</strong> {{ config('app.name') }} is an independent service
                            and is <strong>not affiliated with, endorsed by, or officially connected to
                            WhatsApp or Meta Platforms, Inc.</strong> in any way. It connects to WhatsApp
                            the same way the regular WhatsApp Web application does — it does not use
                            WhatsApp's official Business API. WhatsApp's own Terms of Service, Business
                            Policy, and enforcement systems apply to any number you connect, independently
                            of these Terms. WhatsApp may restrict, rate-limit, or ban a number for reasons
                            outside our control (for example, unusual sending patterns or user reports),
                            and we cannot prevent or reverse that. Do not connect a number you cannot
                            afford to lose access to.
                        </div>
                    </div>
                </section>

                <section id="{{ $id = 'account' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        You must provide accurate information when registering and are responsible
                        for keeping your password, API access tokens, and any connected WhatsApp
                        session secure. You are responsible for all activity that happens under your
                        account and your API tokens, whether or not you personally performed it.
                        Tell us immediately if you believe your account or a token has been
                        compromised — you can also revoke a token yourself at any time from your
                        instance's page.
                    </p>
                </section>

                <section id="{{ $id = 'acceptable-use' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>You agree not to use the Service to:</p>
                    <ul class="tm-list tm-list-no">
                        <li>Send unsolicited bulk messages, spam, or messages to recipients who have not agreed to receive them;</li>
                        <li>Violate WhatsApp's own Terms of Service or Business Policy;</li>
                        <li>Send content that is illegal, fraudulent, threatening, harassing, or infringes someone else's rights;</li>
                        <li>Attempt to bypass, disable, or interfere with WhatsApp's security, rate limits, CAPTCHA, or account-enforcement systems;</li>
                        <li>Attempt to access another user's account, instance, messages, or tokens;</li>
                        <li>Interfere with or disrupt the Service's infrastructure, or attempt to reverse-engineer it beyond what applicable law allows.</li>
                    </ul>
                    <p class="mb-0">
                        We may suspend or terminate access for any account we reasonably believe is
                        violating this section, with or without notice.
                    </p>
                </section>

                <section id="{{ $id = 'bulk-messaging' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>Bulk messaging and blocked numbers — your responsibility</h2>
                    <div class="tm-callout tm-callout-red">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <strong>If WhatsApp blocks or bans your number, that is not our responsibility.</strong>
                            You alone decide what you send, to whom, and how often. Sending bulk or
                            promotional messages, messaging people who haven't asked to hear from you, or
                            sending too many messages too quickly can get your WhatsApp number restricted
                            or permanently banned by WhatsApp — and we have no way to prevent or undo that.
                        </div>
                    </div>
                    <p>By using the Service, you understand and agree that:</p>
                    <ul class="tm-list">
                        <li>
                            <strong>You are solely responsible for every message sent</strong> from your
                            connected number, whether sent through the dashboard, the API, or any tool or
                            integration you connect to it.
                        </li>
                        <li>
                            <strong>We are not liable</strong> for any restriction, suspension, or
                            permanent ban of your WhatsApp number or account by WhatsApp or Meta, or for
                            any loss that follows from it — including lost contacts, chats, customers,
                            sales, or business.
                        </li>
                        <li>
                            <strong>No refunds are given</strong> because a number was restricted or
                            banned. Your subscription remains active, and you may connect a different
                            number to your instance.
                        </li>
                        <li>
                            <strong>You must only message people who have agreed to hear from you</strong>
                            (for example, your own customers who gave you their number), and you must
                            honour anyone who asks you to stop.
                        </li>
                        <li>
                            The Service does not offer — and will not build — any feature designed to
                            avoid WhatsApp's limits or detection. Sending responsibly is the only way to
                            protect your number.
                        </li>
                    </ul>
                    <div class="tm-tip">
                        <i class="bi bi-lightbulb"></i>
                        <div>
                            Good practice: send only messages people expect, keep volumes steady rather than
                            in sudden bursts, avoid identical messages to many recipients, and use a number
                            you can afford to lose while you test.
                        </div>
                    </div>
                </section>

                <section id="{{ $id = 'billing' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        Some features require a paid plan, billed monthly in advance through our
                        payment processor, Cashfree. By subscribing, you authorize us to charge your
                        chosen payment method on a recurring basis until you cancel. You can cancel
                        your subscription at any time from your account's Billing page, or by
                        contacting us; cancellation stops future billing but does not refund amounts
                        already charged for the current period, except where required by law. Fees
                        are shown in Indian Rupees (INR) and are exclusive of any taxes we're required
                        to collect. We may change plan pricing going forward; we'll give you
                        reasonable notice before a price change applies to your existing subscription.
                    </p>
                </section>

                <section id="{{ $id = 'content' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        You keep ownership of the messages and data you send or receive through the
                        Service. We process that content — including message text and phone numbers
                        — only as needed to operate the Service (for example, delivering a message
                        you asked us to send, or forwarding an incoming message to a webhook URL you
                        configured). We do not sell your data. If you connect a webhook URL, you are
                        responsible for how that endpoint stores and handles the data we send it.
                        Deleting your account deletes your instances, API tokens, and message history
                        from our systems, other than what we're legally required to retain.
                        See our <a href="{{ route('privacy') }}">Privacy Policy</a> for details.
                    </p>
                </section>

                <section id="{{ $id = 'availability' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        The Service is provided on an "as is" and "as available" basis. Because it
                        depends on an unofficial connection to WhatsApp's own infrastructure, we
                        cannot guarantee uninterrupted availability, message delivery, or that
                        WhatsApp won't change something on their end that affects the Service. We may
                        modify, suspend, or discontinue any part of the Service at any time.
                    </p>
                </section>

                <section id="{{ $id = 'termination' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        You may stop using the Service and delete your account at any time from your
                        Account page. We may suspend or terminate your access if you violate these
                        Terms, if required by law, or if we discontinue the Service, in which case
                        we'll try to give you reasonable notice where practical.
                    </p>
                </section>

                <section id="{{ $id = 'liability' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        To the fullest extent permitted by law, we disclaim all warranties, express
                        or implied, regarding the Service, including any warranty that it will be
                        uninterrupted, error-free, or that any WhatsApp number will remain connected
                        or unrestricted by WhatsApp. We are not liable for any loss or damage arising
                        from WhatsApp restricting, rate-limiting, or banning a number connected
                        through the Service, from messages not being delivered, or from any indirect,
                        incidental, or consequential damages. Our total liability for any claim
                        relating to the Service is limited to the amount you paid us in the three
                        months before the claim arose.
                    </p>
                </section>

                <section id="{{ $id = 'changes' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        We may update these Terms from time to time. If we make a material change,
                        we'll update the date at the top of this page. Continuing to use the Service
                        after a change means you accept the updated Terms.
                    </p>
                </section>

                <section id="{{ $id = 'law' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p class="mb-0">
                        These Terms are governed by the laws of India, without regard to its
                        conflict-of-law principles.
                    </p>
                </section>

                <section id="{{ $id = 'contact' }}" class="tm-section">
                    <h2 class="tm-h2"><span class="tm-num">{{ ++$n }}</span>{{ $sections[$id] }}</h2>
                    <p>Questions about these Terms? Contact us at:</p>
                    <div class="tm-contact">
                        <span class="ad-stat-icon ad-tone-green"><i class="bi bi-envelope-paper"></i></span>
                        <div style="min-width: 0;">
                            <div class="fw-semibold">BriskBrain Technologies</div>
                            <a href="mailto:briskbraintechnologies@gmail.com" class="text-break">briskbraintechnologies@gmail.com</a>
                            <div class="small text-muted">or use our <a href="{{ route('contact') }}">contact form</a></div>
                        </div>
                    </div>
                </section>
            </div>

            <p class="text-center text-muted small mt-3 mb-0">
                See also our <a href="{{ route('privacy') }}">Privacy Policy</a>.
            </p>
        </div>
    </div>
</div>
@endsection
