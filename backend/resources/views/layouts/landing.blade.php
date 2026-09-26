<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Home') - {{ config('app.name') }}</title>
    @include('partials.seo', ['defaultRobots' => 'index, follow'])

    {{-- Plus Jakarta Sans from Bunny Fonts (privacy-friendly Google Fonts
         mirror, the same host Laravel's own starter pages use). Only the
         public landing page loads it; the app keeps the system font. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    {{-- Lets landing.css hide scroll-reveal elements only when JS will
         actually reveal them again (without JS everything just shows). --}}
    <script>document.documentElement.classList.add('lp-js');</script>

    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js', 'resources/js/landing.js'])
</head>
<body class="landing d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg lp-nav sticky-top" data-lp-nav>
        <div class="container">
            <a class="navbar-brand lp-brand" href="{{ route('home') }}">
                <span class="lp-brand-mark"><i class="bi bi-whatsapp"></i></span>
                <span>{{ config('app.name') }}</span>
            </a>

            <button class="navbar-toggler lp-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#lpNavMenu"
                    aria-controls="lpNavMenu" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>

            <div class="collapse navbar-collapse" id="lpNavMenu">
                <ul class="navbar-nav mx-lg-auto gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2 pt-3 pt-lg-0">
                    @if ($dashboardUrl ?? null)
                        <a class="lp-btn lp-btn-primary lp-btn-sm" href="{{ $dashboardUrl }}">
                            <i class="bi bi-speedometer2"></i> Go to dashboard
                        </a>
                    @else
                        <a class="lp-btn lp-btn-ghost lp-btn-sm" href="{{ route('login') }}">Log in</a>
                        <a class="lp-btn lp-btn-primary lp-btn-sm" href="{{ route('register') }}">Register</a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow-1">
        @if (session('status') || session('error'))
            <div class="container pt-3">
                @if (session('status'))
                    <div class="alert alert-success mb-0">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-0">{{ session('error') }}</div>
                @endif
            </div>
        @endif

        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
