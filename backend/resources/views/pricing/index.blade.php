@extends('layouts.landing')

@section('title', 'WhatsApp API Pricing')
@section('meta_description', 'WhatsApp API plans from free. Compare numbers, monthly messages, chatbot and features. No Meta approval, cancel anytime. Incoming messages are free.')

@php
    $isZar = \App\Support\Currency::isZar();
    $freeLimit = $plans->first()?->messages_per_month;

    // Question => answer (HTML). The same list feeds the FAQPage structured data.
    $faqs = [
        'Is there a free plan?' =>
            'Yes. The Free plan needs no card'.($freeLimit ? ' and includes '.number_format($freeLimit).' messages a month' : '').', so you can connect a number and try the full API before paying anything.',
        'What counts as a message?' =>
            'Every message you <strong>send</strong> counts, whether through the API, a bulk campaign or a chatbot reply. <strong>Incoming messages are free and unlimited</strong>, and checking whether a number is on WhatsApp doesn\'t count either.',
        'What happens when I reach my monthly limit?' =>
            'Sending stops and the API returns a clear error, so nothing goes out by surprise. Receiving messages keeps working. Upgrade to keep sending straight away, or wait for the limit to reset at the start of next month.',
        'Can I change or cancel my plan?' =>
            'Yes, anytime from your Billing page. Cancelling stops future billing and takes you back to the Free plan.',
        'How do I pay?' => $isZar
            ? 'Online payment for South Africa is coming soon. For now, <a href="'.route('contact').'">contact us</a> and we\'ll set up your plan.'
            : 'Paid plans are billed monthly through Cashfree. Prices are in Indian Rupees (INR) and exclude any taxes we\'re required to collect.',
        'Do I need Meta approval or a WhatsApp Business account?' =>
            'No. You link your own WhatsApp number by scanning a QR code, like WhatsApp Web. There are no business documents, no Meta verification and no message templates to get approved.',
    ];

    // Feature comparison: label => [value per plan] or one value for all plans.
    $yes = '<i class="bi bi-check-circle-fill text-success" aria-label="Included"></i>';
    $rows = [
        'WhatsApp numbers' => $plans->map(fn ($p) => number_format($p->instances))->all(),
        'Messages sent per month' => $plans->map(fn ($p) => number_format($p->messages_per_month))->all(),
        'Chatbot entries' => $plans->map(fn ($p) => $p->chatbot_entries > 0 ? number_format($p->chatbot_entries) : '&mdash;')->all(),
        'Incoming messages' => 'Unlimited',
        'REST API (text, images, video, audio, documents)' => $yes,
        'Webhooks for incoming messages' => $yes,
        'Delivery &amp; read status' => $yes,
        'Bulk messages' => $yes,
        'Check if numbers are on WhatsApp' => $yes,
        'Message history &amp; API logs' => $yes,
    ];
@endphp

@push('structured_data')
@include('partials.breadcrumb-schema', ['crumb' => 'Pricing'])
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

@section('content')
{{-- ============================ PLANS ============================ --}}
<section class="lp-section">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-tag"></i> Pricing</span>
            <h1 class="lp-h2">WhatsApp API pricing</h1>
            <p class="lp-sub">
                Start free, upgrade when you outgrow it. Monthly plans, cancel anytime &mdash;
                and incoming messages never count toward your limit.
            </p>
        </div>

        @include('partials.pricing-cards')

        @if ($isZar)
            <p class="text-center small text-muted mt-4 mb-0">
                Prices in South African Rand (ZAR). Online payment is coming soon &mdash;
                <a href="{{ route('contact') }}">contact us</a> to subscribe to a paid plan.
            </p>
        @else
            <p class="text-center small text-muted mt-4 mb-0">Prices in Indian Rupees (INR), billed monthly through Cashfree. Taxes extra where applicable.</p>
        @endif
    </div>
</section>

{{-- ========================== COMPARISON ========================= --}}
<section class="lp-section lp-section-ice" id="compare">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-table"></i> Compare</span>
            <h2 class="lp-h2">Compare plans</h2>
            <p class="lp-sub">Every plan gets the full API. Plans differ only in numbers, message volume and chatbot size.</p>
        </div>

        <p class="small text-muted text-center d-md-none mb-2"><i class="bi bi-arrow-left-right me-1"></i>Swipe sideways to compare all plans</p>
        <div class="card shadow-sm border-0 overflow-hidden" data-reveal>
            <div class="table-responsive">
                {{-- On phones the plan columns scroll; the feature column stays put. --}}
                <table class="table align-middle mb-0 text-center pr-compare">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-start">Feature</th>
                            @foreach ($plans as $plan)
                                <th scope="col">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row" class="text-start fw-semibold">Price per month</th>
                            @foreach ($plans as $plan)
                                @php($local = \App\Models\Plan::localPrice($plan))
                                <td class="fw-semibold text-nowrap">
                                    @if ($local === null)
                                        <a href="{{ route('contact') }}">Contact us</a>
                                    @elseif ($local > 0)
                                        {{ \App\Support\Currency::symbol() }}{{ number_format($local) }}
                                    @else
                                        Free
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @foreach ($rows as $label => $values)
                            <tr>
                                <th scope="row" class="text-start fw-normal">{!! $label !!}</th>
                                @foreach ($plans as $key => $plan)
                                    <td>{!! is_array($values) ? $values[$key] : $values !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

{{-- ============================== FAQ ============================ --}}
<section class="lp-section" id="faq">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-question-circle"></i> FAQ</span>
            <h2 class="lp-h2">Pricing questions</h2>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
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

                <p class="text-center mt-4 mb-0">
                    More questions? See the <a href="{{ route('home') }}#faq">main FAQ</a>,
                    the <a href="{{ route('docs.index') }}">API docs</a> or <a href="{{ route('contact') }}">contact us</a>.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ============================== CTA ============================ --}}
<section class="lp-section pt-0">
    <div class="container">
        <div class="lp-cta text-center" data-reveal>
            <h2 class="lp-h2 mb-2 position-relative">Start free today</h2>
            <p class="lp-sub mb-4 position-relative">Connect your WhatsApp number in minutes. No card needed for the Free plan.</p>
            <a href="{{ $dashboardUrl ?? route('register') }}" class="lp-btn lp-btn-primary lp-btn-lg position-relative">
                @if ($dashboardUrl)
                    <i class="bi bi-speedometer2"></i> Go to your dashboard
                @else
                    <i class="bi bi-rocket-takeoff"></i> Create your free account
                @endif
                <i class="bi bi-arrow-right lp-btn-arrow"></i>
            </a>
        </div>
    </div>
</section>
@endsection

