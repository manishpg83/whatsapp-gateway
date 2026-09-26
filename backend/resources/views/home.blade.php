@extends('layouts.landing')

@section('title', 'WhatsApp API for Developers')
@section('og_title', 'WhatsApp API for Developers — Send & Receive Messages via REST API')
@section('meta_description', 'Connect your own WhatsApp number by QR code and send & receive WhatsApp messages, media and webhooks through a simple REST API. Free plan, live in minutes, no Business API approval.')

@push('structured_data')
@php
    // Structured data (schema.org) for search engines: who runs the site,
    // the site itself, and the product with its real plan prices.
    $offers = $plans->map(fn ($plan) => [
        '@type' => 'Offer',
        'name' => $plan->name,
        'price' => (string) $plan->price,
        'priceCurrency' => 'INR',
        'description' => $plan->instances.' '.Str::plural('instance', $plan->instances).', '.number_format($plan->messages_per_month).' messages/month',
        'url' => route('home').'#pricing',
    ])->values()->all();

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => route('home').'#organization',
                'name' => 'BriskBrain Technologies',
                'url' => route('home'),
                'logo' => asset('images/og-image.png'),
                'email' => 'briskbraintechnologies@gmail.com',
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'email' => 'briskbraintechnologies@gmail.com',
                    'url' => route('contact'),
                    'areaServed' => 'IN',
                    'availableLanguage' => ['English'],
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => route('home').'#website',
                'name' => config('app.name'),
                'url' => route('home'),
                'inLanguage' => 'en-IN',
                'publisher' => ['@id' => route('home').'#organization'],
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => config('app.name'),
                'applicationCategory' => 'DeveloperApplication',
                'operatingSystem' => 'Web',
                'url' => route('home'),
                'description' => 'A REST API to send and receive WhatsApp messages from your own WhatsApp number, with webhooks for incoming messages.',
                'featureList' => [
                    'Connect a WhatsApp number by scanning a QR code',
                    'Send text and media messages through a REST API',
                    'Receive incoming messages via webhooks',
                    'Check whether a number is on WhatsApp',
                    'Message history and API logs',
                ],
                'offers' => $offers,
                'publisher' => ['@id' => route('home').'#organization'],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
@php
    // Deterministic little "QR code" for the hero's QR card (decorative only).
    $qrCells = [];
    for ($y = 0; $y < 17; $y++) {
        for ($x = 0; $x < 17; $x++) {
            $inFinder = ($x < 5 && $y < 5) || ($x > 11 && $y < 5) || ($x < 5 && $y > 11);
            if (! $inFinder && (($x * 7 + $y * 13 + $x * $y) % 5) < 2) {
                $qrCells[] = [$x, $y];
            }
        }
    }
@endphp

{{-- ============================ HERO ============================ --}}
<section class="lp-hero">
    <div class="lp-hero-bg" aria-hidden="true">
        <span class="lp-orb lp-orb-1"></span>
        <span class="lp-orb lp-orb-2"></span>
        <span class="lp-orb lp-orb-3"></span>
        <span class="lp-grid-fade"></span>
    </div>

    <div class="container position-relative">
        <div class="row align-items-center gy-5">
            <div class="col-xl-6">
                <span class="lp-eyebrow lp-hero-in" style="--d: 0ms;">
                    <i class="bi bi-lightning-charge-fill"></i> Developer messaging platform
                </span>

                <h1 class="lp-hero-title lp-hero-in" style="--d: 80ms;">
                    WhatsApp messaging API for your product,
                    <span class="lp-highlight">without the Business API paperwork</span>
                </h1>

                <p class="lp-hero-lead lp-hero-in" style="--d: 160ms;">
                    Connect your own WhatsApp number, scan a QR code, and start sending &amp; receiving
                    messages through a simple REST API &mdash; live in minutes, not weeks of approval.
                </p>

                <div class="d-flex flex-column flex-sm-row gap-3 lp-hero-in" style="--d: 240ms;">
                    @if ($dashboardUrl)
                        <a href="{{ $dashboardUrl }}" class="lp-btn lp-btn-primary lp-btn-lg">
                            <i class="bi bi-speedometer2"></i> Go to your dashboard
                            <i class="bi bi-arrow-right lp-btn-arrow"></i>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-lg">
                            <i class="bi bi-rocket-takeoff"></i> Create your free account
                            <i class="bi bi-arrow-right lp-btn-arrow"></i>
                        </a>
                        <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost lp-btn-lg">Log in</a>
                    @endif
                </div>

                <ul class="lp-trust lp-hero-in" style="--d: 320ms;">
                    <li><i class="bi bi-check-circle-fill"></i> No credit card required</li>
                    <li><i class="bi bi-check-circle-fill"></i> Free plan available</li>
                    <li><i class="bi bi-check-circle-fill"></i> Live in minutes</li>
                </ul>
            </div>

            {{-- Hero graphic: phone chat + the pieces of the product around it
                 (API call, webhook, QR pairing, usage chart). Decorative. --}}
            <div class="col-xl-6">
                <div class="lp-stage" aria-hidden="true">
                    <svg class="lp-stage-lines" viewBox="0 0 560 560" preserveAspectRatio="none">
                        <path d="M150 150 C 200 170, 210 210, 250 230" />
                        <path d="M420 190 C 380 200, 370 230, 330 250" />
                        <path d="M140 430 C 190 420, 210 390, 240 370" />
                        <path d="M430 420 C 390 410, 370 390, 330 370" />
                    </svg>

                    {{-- Phone with a live-looking chat --}}
                    <div class="lp-phone">
                        <div class="lp-phone-notch"></div>
                        <div class="lp-chat-head">
                            <span class="lp-chat-avatar"><i class="bi bi-shop"></i></span>
                            <span class="lh-sm">
                                <span class="d-block fw-semibold">Acme Store</span>
                                <span class="lp-chat-online">online</span>
                            </span>
                            <i class="bi bi-three-dots-vertical ms-auto"></i>
                        </div>
                        <div class="lp-chat-body">
                            <div class="lp-bubble lp-bubble-in" style="--d: 700ms;">
                                Hi! Is my order #4821 shipped?
                                <span class="lp-bubble-time">10:24</span>
                            </div>
                            <div class="lp-bubble lp-bubble-out" style="--d: 1500ms;">
                                Yes! &#x1F69A; It's on the way and arrives tomorrow.
                                <span class="lp-bubble-time">10:24 <i class="bi bi-check2-all"></i></span>
                                <span class="lp-bubble-tag"><i class="bi bi-code-slash"></i> sent via API</span>
                            </div>
                            <div class="lp-bubble lp-bubble-in" style="--d: 2400ms;">
                                Great, thank you &#x1F64F;
                                <span class="lp-bubble-time">10:25</span>
                            </div>
                            <div class="lp-typing" style="--d: 3100ms;"><span></span><span></span><span></span></div>
                        </div>
                        <div class="lp-chat-input">
                            <span>Type a message</span>
                            <span class="lp-chat-send"><i class="bi bi-send-fill"></i></span>
                        </div>
                    </div>

                    {{-- API call --}}
                    <div class="lp-float lp-float-api" style="--d: 300ms; --f: 7s;">
                        <div class="lp-code-head">
                            <span class="lp-method">POST</span>
                            <span class="lp-path">/api/v1/messages/send</span>
                            <span class="lp-ok">200</span>
                        </div>
<pre class="lp-code"><span class="k">"to"</span>: <span class="s">"919876543210"</span>,
<span class="k">"message"</span>: <span class="s">"Yes! It's on the way&hellip;"</span></pre>
                    </div>

                    {{-- Webhook --}}
                    <div class="lp-float lp-float-webhook" style="--d: 500ms; --f: 8s;">
                        <span class="lp-float-icon lp-icon-blue"><i class="bi bi-diagram-3"></i></span>
                        <span class="lh-sm">
                            <span class="d-block fw-semibold">Webhook delivered</span>
                            <span class="lp-float-sub">message.received &middot; <span class="text-success fw-semibold">200 OK</span></span>
                        </span>
                    </div>

                    {{-- QR pairing --}}
                    <div class="lp-float lp-float-qr" style="--d: 700ms; --f: 9s;">
                        <svg class="lp-qr" viewBox="0 0 17 17" shape-rendering="crispEdges">
                            @foreach ([[0, 0], [12, 0], [0, 12]] as [$fx, $fy])
                                <rect x="{{ $fx }}" y="{{ $fy }}" width="5" height="5" rx="1" class="lp-qr-ring" />
                                <rect x="{{ $fx + 1.5 }}" y="{{ $fy + 1.5 }}" width="2" height="2" rx=".4" />
                            @endforeach
                            @foreach ($qrCells as [$x, $y])
                                <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" />
                            @endforeach
                        </svg>
                        <span class="lh-sm">
                            <span class="d-block fw-semibold">Scan to connect</span>
                            <span class="lp-float-sub text-success"><i class="bi bi-check-circle-fill"></i> Connected</span>
                        </span>
                    </div>

                    {{-- Usage chart --}}
                    <div class="lp-float lp-float-chart" style="--d: 900ms; --f: 7.5s;">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <span class="lp-float-sub">Messages this week</span>
                            <span class="fw-bold" data-count-to="1284">1,284</span>
                        </div>
                        <div class="lp-bars">
                            @foreach ([40, 62, 48, 78, 58, 92, 72] as $i => $h)
                                <span style="--h: {{ $h }}%; --i: {{ $i }};"></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="lp-float lp-float-wa" style="--d: 200ms; --f: 6s;"><i class="bi bi-whatsapp"></i></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ======================== HOW IT WORKS ======================== --}}
<section class="lp-section" id="how-it-works">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-signpost-2"></i> How it works</span>
            <h2 class="lp-h2">From sign-up to your first message in four steps</h2>
            <p class="lp-sub">No approval process, no new phone number: just the WhatsApp you already use.</p>
        </div>

        <div class="lp-steps" data-animate>
            <div class="lp-steps-line" aria-hidden="true"><span></span></div>

            @foreach ([
                ['bi-qr-code-scan', 'Create an instance and scan a QR code', 'Link your own WhatsApp number with the app you already use on your phone.'],
                ['bi-key', 'Generate an API token', 'A cryptographically random token, shown once, scoped to that instance only.'],
                ['bi-send', 'Send messages through the REST API', 'Call one endpoint to send text, images, video, voice notes or documents.'],
                ['bi-diagram-3', 'Receive replies & manage', 'Configure a webhook to receive replies, and manage everything from the dashboard.'],
            ] as $i => [$icon, $title, $text])
                <div class="lp-step" data-reveal style="--i: {{ $i }};">
                    <div class="lp-step-top">
                        <span class="lp-step-num">{{ sprintf('%02d', $i + 1) }}</span>
                        <span class="lp-step-icon"><i class="bi {{ $icon }}"></i></span>
                    </div>
                    <h3 class="lp-step-title">{{ $title }}</h3>
                    <p class="lp-step-text">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========================== FEATURES ========================== --}}
<section class="lp-section lp-section-ice" id="features">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-stars"></i> Features</span>
            <h2 class="lp-h2">Everything you need to put <span class="lp-highlight">WhatsApp in your product</span></h2>
            <p class="lp-sub">Built for developers and businesses who want a simple, reliable messaging API.</p>
        </div>

        <div class="row g-4">
            @foreach ([
                ['bi-code-slash', 'green', 'Simple REST API', 'One endpoint to send a message, authenticated with a bearer token.'],
                ['bi-link-45deg', 'blue', 'Incoming webhooks', 'Get replies delivered to your own endpoint, signed so you can verify them.'],
                ['bi-hdd-stack', 'purple', 'Multiple instances', 'Connect more than one WhatsApp number, each fully independent.'],
                ['bi-speedometer2', 'teal', 'A real dashboard', 'Manage instances, tokens, and usage without touching a terminal.'],
            ] as $i => [$icon, $tone, $title, $text])
                <div class="col-sm-6 col-lg-3">
                    <div class="lp-feature" data-reveal style="--i: {{ $i }};">
                        <span class="lp-feature-icon lp-tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                        <h3 class="lp-feature-title">{{ $title }}</h3>
                        <p class="lp-feature-text">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Showcase: the dashboard on a laptop --}}
        <div class="lp-showcase">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5" data-reveal>
                    <span class="lp-eyebrow"><i class="bi bi-window-sidebar"></i> The dashboard</span>
                    <h2 class="lp-h2">See every number, message and webhook in one place</h2>
                    <ul class="lp-checklist">
                        <li><i class="bi bi-qr-code"></i><span><strong>Connect with a QR code.</strong> Disconnect and reconnect without scanning again.</span></li>
                        <li><i class="bi bi-shield-lock"></i><span><strong>Tokens shown once.</strong> Stored hashed and revocable any time.</span></li>
                        <li><i class="bi bi-check2-all"></i><span><strong>Delivery &amp; read status</strong> for every message you send.</span></li>
                        <li><i class="bi bi-activity"></i><span><strong>Connection history</strong> and an email if a number goes offline.</span></li>
                    </ul>
                </div>

                <div class="col-lg-7" data-reveal style="--i: 1;">
                    <div class="lp-laptop" aria-hidden="true">
                        <div class="lp-laptop-screen">
                            <div class="lp-app">
                                <div class="lp-app-side">
                                    <span class="lp-app-logo"><i class="bi bi-whatsapp"></i></span>
                                    <span class="active"><i class="bi bi-house-door"></i></span>
                                    <span><i class="bi bi-hdd-stack"></i></span>
                                    <span><i class="bi bi-chat-left-text"></i></span>
                                    <span><i class="bi bi-code-slash"></i></span>
                                </div>
                                <div class="lp-app-main">
                                    <div class="lp-app-stats">
                                        <div><span>Instances</span><strong data-count-to="3">3</strong></div>
                                        <div><span>Sent</span><strong data-count-to="1284">1,284</strong></div>
                                        <div><span>Received</span><strong data-count-to="467">467</strong></div>
                                    </div>
                                    <div class="lp-app-chart">
                                        <svg viewBox="0 0 300 70" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="lpArea" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0" stop-color="#10b981" stop-opacity=".28" />
                                                    <stop offset="1" stop-color="#10b981" stop-opacity="0" />
                                                </linearGradient>
                                            </defs>
                                            <path class="lp-area" d="M0 55 L40 48 L80 52 L120 34 L160 40 L200 22 L240 28 L300 10 L300 70 L0 70 Z" fill="url(#lpArea)" />
                                            <path class="lp-line" d="M0 55 L40 48 L80 52 L120 34 L160 40 L200 22 L240 28 L300 10" />
                                        </svg>
                                    </div>
                                    <div class="lp-app-rows">
                                        <div><span class="lp-dot ok"></span>Sales <em>Connected</em></div>
                                        <div><span class="lp-dot ok"></span>Support <em>Connected</em></div>
                                        <div><span class="lp-dot warn"></span>Marketing <em class="warn">Reconnecting&hellip;</em></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lp-laptop-base"></div>
                        <div class="lp-float lp-laptop-badge" style="--f: 7s;">
                            <span class="lp-float-icon lp-icon-green"><i class="bi bi-check2-all"></i></span>
                            <span class="lh-sm"><span class="d-block fw-semibold">Message read</span><span class="lp-float-sub">just now</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ========================== PRICING =========================== --}}
<section class="lp-section" id="pricing">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-tag"></i> Pricing</span>
            <h2 class="lp-h2">Simple, transparent pricing</h2>
            <p class="lp-sub">Start free, upgrade whenever you outgrow it.</p>
        </div>

        <div class="row g-4 align-items-stretch">
            @foreach ($plans as $key => $plan)
                @php
                    $isPopular = ! empty($plan['popular']);
                    $tierIcon = match ($key) {
                        'free' => 'bi-gift',
                        'starter' => 'bi-lightning-charge',
                        'growth' => 'bi-graph-up-arrow',
                        default => 'bi-building',
                    };
                @endphp
                <div class="col-sm-6 col-xl-3">
                    <div class="lp-price {{ $isPopular ? 'lp-price-popular' : '' }}" data-reveal style="--i: {{ $loop->index }};">
                        @if ($isPopular)
                            <span class="lp-price-badge"><i class="bi bi-star-fill"></i> Most popular</span>
                        @endif
                        <span class="lp-price-icon"><i class="bi {{ $tierIcon }}"></i></span>
                        <div class="lp-price-name">{{ $plan['name'] }}</div>
                        <p class="lp-price-desc">{{ $plan['description'] }}</p>
                        <div class="lp-price-amount">
                            @if ($plan['price'] > 0)
                                &#8377;{{ number_format($plan['price']) }}<span>/mo</span>
                            @else
                                Free
                            @endif
                        </div>
                        <ul class="lp-price-list">
                            <li><i class="bi bi-check-lg"></i>{{ $plan['instances'] }} instance{{ $plan['instances'] > 1 ? 's' : '' }}</li>
                            <li><i class="bi bi-check-lg"></i>{{ number_format($plan['messages_per_month']) }} messages/mo</li>
                            <li><i class="bi bi-check-lg"></i>Full REST API access</li>
                            <li><i class="bi bi-check-lg"></i>Webhook delivery</li>
                        </ul>
                        <a href="{{ $dashboardUrl ?? route('register') }}" class="lp-btn {{ $isPopular ? 'lp-btn-primary' : 'lp-btn-outline' }} w-100 mt-auto">{{ $dashboardUrl ? 'Go to dashboard' : 'Get started' }}</a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Unofficial-integration disclosure --}}
        <div class="lp-note" data-reveal>
            <i class="bi bi-info-circle"></i>
            <div>
                This is an unofficial WhatsApp Web-style integration, not affiliated with or endorsed by
                WhatsApp/Meta &mdash; not the official WhatsApp Business Cloud API. See our
                <a href="{{ route('terms') }}">Terms of Service</a> for details.
            </div>
        </div>
    </div>
</section>

{{-- ============================= FAQ ============================ --}}
@php
    // Question => answer (HTML). The same list feeds the FAQPage structured
    // data pushed below, so what search engines read always matches the page.
    $faqs = [
        'Is this the official WhatsApp Business API?' =>
            'No. '.e(config('app.name')).' is an independent service and is not affiliated with WhatsApp or Meta. It links to your number the same way WhatsApp Web does, as a linked device. That\'s why there\'s no Meta approval process and you can start in minutes.',
        'Can my WhatsApp number get banned?' =>
            'It can, if WhatsApp sees behaviour it treats as spam, such as bulk or unsolicited messages or sudden bursts of identical texts. Only message people who expect to hear from you, keep volumes steady, and test with a number you can afford to lose. WhatsApp decides bans, not us, and we can\'t undo them. See <a href="'.route('terms').'#bulk-messaging">section 4 of our Terms</a>.',
        'Do I need a WhatsApp Business account or Meta verification?' =>
            'No. Both a regular WhatsApp number and a WhatsApp Business app number work. There are no business documents, no Meta verification and no message templates to get approved.',
        'How do I connect my number?' =>
            'Create an instance in your dashboard and scan the QR code with WhatsApp on your phone (<em>Settings → Linked devices → Link a device</em>), just like WhatsApp Web. Once it shows <strong>Connected</strong>, generate an API token and you\'re ready to send.',
        'Can I receive incoming messages?' =>
            'Yes. Set a webhook URL on your instance and every incoming message is sent to your server as it arrives, including photos, videos, voice notes and documents. You can also see them in the Messages page of your dashboard.',
        'What can I send besides text?' =>
            'Images, videos, audio, voice notes and documents. Pass a public file URL or upload the file directly with the same API call, and add an optional caption to images, videos and documents.',
        'Which programming languages can I use?' =>
            'Any language that can make an HTTP request. It\'s a plain REST API with JSON. The API Docs include ready-to-copy examples for curl, PHP, Python, JavaScript, Java and .NET.',
        'Is my data safe? Do you read my messages?' =>
            'Your messages are only visible to your own account, API tokens are stored hashed, and each WhatsApp session is kept isolated outside the public web directory. We don\'t read your messages for marketing, sell your data or use them to train AI. See our <a href="'.route('privacy').'">Privacy Policy</a>.',
        'What happens when I reach my monthly message limit?' =>
            'The API stops sending and returns a clear error telling you the limit was reached, so nothing is sent by surprise. Upgrade your plan from the Billing page to keep sending right away. Checking whether a number is on WhatsApp doesn\'t count toward the limit.',
        'Is there a free plan, and can I cancel anytime?' =>
            'Yes. The Free plan needs no credit card. Paid plans are billed monthly through Cashfree and you can cancel from your Billing page at any time, which stops future billing.',
    ];
@endphp

@push('structured_data')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => collect($faqs)->map(fn ($answer, $question) => [
        '@type' => 'Question',
        'name' => $question,
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
    ])->values()->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

<section class="lp-section lp-section-ice" id="faq">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="lp-faq-intro" data-reveal>
                    <span class="lp-eyebrow"><i class="bi bi-question-circle"></i> FAQ</span>
                    <h2 class="lp-h2">Questions, answered</h2>
                    <p class="lp-sub mb-4">Everything people usually ask before connecting their first number.</p>

                    <div class="lp-faq-help">
                        <span class="lp-faq-help-icon"><i class="bi bi-headset"></i></span>
                        <div>
                            <div class="fw-bold">Still have a question?</div>
                            <div class="small text-muted mb-2">A real person replies within 1 business day.</div>
                            <a href="{{ route('contact') }}" class="lp-faq-help-link">Contact us <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="lp-faq">
                    @foreach ($faqs as $question => $answer)
                        <details class="lp-faq-item" data-reveal style="--i: {{ $loop->index % 4 }};" @if ($loop->first) open @endif>
                            <summary class="lp-faq-q">
                                <span class="lp-faq-num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="flex-grow-1">{{ $question }}</span>
                                <span class="lp-faq-toggle" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                            </summary>
                            <div class="lp-faq-a">{!! $answer !!}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ========================== FINAL CTA ========================= --}}
<section class="lp-section pt-0">
    <div class="container">
        <div class="lp-cta" data-reveal>
            <svg class="lp-cta-curves" viewBox="0 0 1200 400" preserveAspectRatio="none" aria-hidden="true">
                <path d="M-20 320 C 200 200, 380 380, 620 260 S 1000 120, 1220 220" />
                <path d="M-20 90 C 240 20, 420 170, 700 90 S 1060 10, 1220 60" />
            </svg>

            <div class="row align-items-center gy-4 position-relative">
                <div class="col-lg-5 order-lg-2">
                    <div class="lp-cta-art" aria-hidden="true">
                        <span class="lp-cta-wa lp-float" style="--f: 6s;"><i class="bi bi-whatsapp"></i></span>
                        <div class="lp-cta-card lp-float" style="--f: 8s;">
                            <span class="lp-float-icon lp-icon-green"><i class="bi bi-plug"></i></span>
                            <span class="lh-sm"><span class="d-block fw-semibold">Instance connected</span><span class="lp-float-sub">Ready to send</span></span>
                        </div>
                        <div class="lp-cta-bubble lp-float" style="--f: 7s;">Your first message is one API call away &#x1F44B;</div>
                    </div>
                </div>
                <div class="col-lg-7 order-lg-1">
                    <h2 class="lp-h2 mb-2">Ready to connect your WhatsApp number?</h2>
                    <p class="lp-sub mb-4">No credit card required to get started on the free plan.</p>
                    @if ($dashboardUrl)
                        <a href="{{ $dashboardUrl }}" class="lp-btn lp-btn-primary lp-btn-lg">
                            <i class="bi bi-speedometer2"></i> Go to your dashboard
                            <i class="bi bi-arrow-right lp-btn-arrow"></i>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-lg">
                            <i class="bi bi-rocket-takeoff"></i> Create your free account
                            <i class="bi bi-arrow-right lp-btn-arrow"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
