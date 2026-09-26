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
    @include('partials.site-nav', ['onLanding' => true])

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
