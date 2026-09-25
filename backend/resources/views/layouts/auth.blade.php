<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Account') - {{ config('app.name') }}</title>

    {{-- Same font + styles as the landing page (layouts/landing.blade.php). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="landing lp-auth">
<div class="lp-auth-shell">
    {{-- Left: the form --}}
    <div class="lp-auth-main">
        <header class="lp-auth-top">
            <a class="lp-brand text-decoration-none" href="{{ route('home') }}">
                <span class="lp-brand-mark"><i class="bi bi-whatsapp"></i></span>
                <span>{{ config('app.name') }}</span>
            </a>

            @auth
                {{-- Logged in but not verified yet (verify-email page). --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="lp-btn lp-btn-ghost lp-btn-sm"><i class="bi bi-box-arrow-right"></i> Log out</button>
                </form>
            @else
                <a href="{{ route('home') }}" class="lp-auth-back"><i class="bi bi-arrow-left"></i> Back to home</a>
            @endauth
        </header>

        <div class="lp-auth-form-wrap">
            <div class="lp-auth-form">
                @if (session('status'))
                    <div class="lp-auth-alert lp-auth-alert-success"><i class="bi bi-check-circle-fill"></i><span>{{ session('status') }}</span></div>
                @endif
                @if (session('error'))
                    <div class="lp-auth-alert lp-auth-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><span>{{ session('error') }}</span></div>
                @endif

                @yield('content')
            </div>
        </div>

        <footer class="lp-auth-foot">
            <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
            <span class="d-flex flex-wrap gap-3">
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('terms') }}">Terms</a>
                <a href="{{ route('privacy') }}">Privacy</a>
            </span>
        </footer>
    </div>

    {{-- Right: brand panel (large screens only). Decorative. --}}
    <aside class="lp-auth-aside" aria-hidden="true">
        <span class="lp-orb lp-orb-1"></span>
        <span class="lp-orb lp-orb-2"></span>
        <span class="lp-grid-fade"></span>

        <div class="lp-auth-aside-inner">
            <div class="lp-auth-art">
                <div class="lp-auth-phone">
                    <div class="lp-phone-notch"></div>
                    <div class="lp-chat-head">
                        <span class="lp-chat-avatar"><i class="bi bi-shop"></i></span>
                        <span class="lh-sm">
                            <span class="d-block fw-semibold">Acme Store</span>
                            <span class="lp-chat-online">online</span>
                        </span>
                    </div>
                    <div class="lp-chat-body">
                        <div class="lp-bubble lp-bubble-in" style="--d: 500ms;">
                            Hi! Is my order #4821 shipped?
                            <span class="lp-bubble-time">10:24</span>
                        </div>
                        <div class="lp-bubble lp-bubble-out" style="--d: 1300ms;">
                            Yes! &#x1F69A; It's on the way.
                            <span class="lp-bubble-time">10:24 <i class="bi bi-check2-all"></i></span>
                            <span class="lp-bubble-tag"><i class="bi bi-code-slash"></i> sent via API</span>
                        </div>
                        <div class="lp-typing" style="--d: 2100ms;"><span></span><span></span><span></span></div>
                    </div>
                </div>

                <div class="lp-float lp-code-card lp-auth-card-api" style="--d: 300ms; --f: 7s;">
                    <div class="lp-code-head">
                        <span class="lp-method">POST</span>
                        <span class="lp-path">/api/v1/messages/send</span>
                        <span class="lp-ok">200</span>
                    </div>
<pre class="lp-code"><span class="k">"to"</span>: <span class="s">"919876543210"</span>,
<span class="k">"message"</span>: <span class="s">"Yes! It's on the way&hellip;"</span></pre>
                </div>

                <div class="lp-float lp-auth-card-webhook" style="--d: 600ms; --f: 8s;">
                    <span class="lp-float-icon lp-icon-blue"><i class="bi bi-diagram-3"></i></span>
                    <span class="lh-sm">
                        <span class="d-block fw-semibold">Webhook delivered</span>
                        <span class="lp-float-sub">message.received &middot; <span class="text-success fw-semibold">200 OK</span></span>
                    </span>
                </div>

                <div class="lp-float lp-auth-card-connected" style="--d: 900ms; --f: 9s;">
                    <span class="lp-float-icon lp-icon-green"><i class="bi bi-plug"></i></span>
                    <span class="lh-sm">
                        <span class="d-block fw-semibold">Instance connected</span>
                        <span class="lp-float-sub">Ready to send</span>
                    </span>
                </div>
            </div>

            <h2 class="lp-auth-aside-title">WhatsApp messaging for your product, <span class="lp-highlight">live in minutes</span></h2>
            <ul class="lp-trust justify-content-center">
                <li><i class="bi bi-check-circle-fill"></i> No credit card required</li>
                <li><i class="bi bi-check-circle-fill"></i> Free plan available</li>
                <li><i class="bi bi-check-circle-fill"></i> Simple REST API</li>
            </ul>
        </div>
    </aside>
</div>
</body>
</html>
