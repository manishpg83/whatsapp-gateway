@extends('layouts.landing')

@section('title', 'About Us')
@section('meta_description', 'InstaMessage is built and run by BriskBrain Technologies, a software company in Ahmedabad, India. Company details, address and how to reach us.')

@php
    $company = config('company');
    $address = $company['address'];
    $addressLines = [$address['street'], "{$address['city']}, {$address['region']} {$address['postal_code']}", $address['country']];
@endphp

@push('structured_data')
@include('partials.breadcrumb-schema', ['crumb' => 'About us'])
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'AboutPage',
    'name' => 'About '.config('app.name'),
    'url' => url()->current(),
    'about' => [
        '@type' => 'Organization',
        '@id' => route('home').'#organization',
        'name' => $company['name'],
        'url' => $company['website'],
        'email' => $company['email'],
        'telephone' => $company['phone_e164'],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $address['street'],
            'addressLocality' => $address['city'],
            'addressRegion' => $address['region'],
            'postalCode' => $address['postal_code'],
            'addressCountry' => $address['country_code'],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<section class="lp-section">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-building"></i> About us</span>
            <h1 class="lp-h2">About {{ config('app.name') }}</h1>
            <p class="lp-sub">
                {{ config('app.name') }} is built and run by {{ $company['name'] }},
                a software company in {{ $address['city'] }}, {{ $address['country'] }}.
            </p>
        </div>

        <div class="row g-4 justify-content-center">
            {{-- Who we are / what we do --}}
            <div class="col-lg-7" data-reveal>
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h4 fw-bold mb-3">What we do</h2>
                        <p>
                            {{ config('app.name') }} lets developers and businesses send and receive WhatsApp messages
                            through a simple REST API, using their own WhatsApp number. You connect a number by scanning a
                            QR code, the same way WhatsApp Web works, and you can be sending messages in minutes &mdash;
                            with webhooks for incoming messages, bulk messages and a keyword chatbot.
                        </p>

                        <h2 class="h4 fw-bold mt-4 mb-3">How we work</h2>
                        <ul class="mb-0 ps-3">
                            <li class="mb-2">
                                <strong>Honest about what this is.</strong> {{ config('app.name') }} is an independent service,
                                not affiliated with WhatsApp or Meta, and not the official WhatsApp Business Cloud API.
                            </li>
                            <li class="mb-2">
                                <strong>Responsible messaging.</strong> We help you message people who expect to hear from you,
                                and we don't build anything designed to get around WhatsApp's limits or rules.
                                See our <a href="{{ route('docs.index') }}#responsible-use">guide to protecting your number</a>.
                            </li>
                            <li>
                                <strong>Your data stays yours.</strong> Your messages are only visible to your own account,
                                API tokens are stored hashed, and we don't sell your data.
                                See our <a href="{{ route('privacy') }}">Privacy Policy</a>.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Company details --}}
            <div class="col-lg-5" data-reveal style="--i: 1;">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h4 fw-bold mb-4">Company details</h2>

                        <div class="d-flex gap-3 mb-3">
                            <i class="bi bi-building text-primary fs-5"></i>
                            <div>
                                <div class="small text-muted">Company</div>
                                <div class="fw-semibold">{{ $company['name'] }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <i class="bi bi-geo-alt text-primary fs-5"></i>
                            <div>
                                <div class="small text-muted">Address</div>
                                <address class="mb-0">
                                    @foreach ($addressLines as $line)
                                        {{ $line }}@if (! $loop->last)<br>@endif
                                    @endforeach
                                </address>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <i class="bi bi-telephone text-primary fs-5"></i>
                            <div>
                                <div class="small text-muted">Phone</div>
                                <a href="tel:{{ $company['phone_e164'] }}" class="text-decoration-none">{{ $company['phone'] }}</a>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <i class="bi bi-envelope text-primary fs-5"></i>
                            <div>
                                <div class="small text-muted">Email</div>
                                <a href="mailto:{{ $company['email'] }}" class="text-decoration-none text-break">{{ $company['email'] }}</a>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <i class="bi bi-globe2 text-primary fs-5"></i>
                            <div>
                                <div class="small text-muted">Company website</div>
                                <a href="{{ $company['website'] }}" class="text-decoration-none" target="_blank" rel="noopener">{{ preg_replace('#^https?://|/$#', '', $company['website']) }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5" data-reveal>
            <a href="{{ route('contact') }}" class="lp-btn lp-btn-primary">
                <i class="bi bi-chat-dots"></i> Contact us
            </a>
            <a href="{{ route('pricing') }}" class="lp-btn lp-btn-outline ms-2">See pricing</a>
        </div>
    </div>
</section>
@endsection
