{{-- The public site's top bar: the landing page (layouts/landing) and the
     guest version of Terms / Privacy / Contact (layouts/app). Styles are in
     landing.css; the `landing` class on the <nav> itself gives it the
     landing colours and font on any page.
     $onLanding (default false): section links are plain #anchors on the
     landing page, and point back to it (/#pricing) everywhere else. --}}
@php
    $onLanding = $onLanding ?? false;
    $anchor = fn (string $id) => $onLanding ? '#'.$id : route('home').'#'.$id;
    $user = auth()->user();
@endphp
<nav class="landing navbar navbar-expand-lg lp-nav sticky-top {{ $onLanding ? '' : 'is-scrolled' }}" @if ($onLanding) data-lp-nav @endif>
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
                <li class="nav-item"><a class="nav-link" href="{{ $anchor('features') }}">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $anchor('how-it-works') }}">How it works</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $anchor('pricing') }}">Pricing</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $anchor('faq') }}">FAQ</a></li>
            </ul>
            <div class="d-flex flex-column flex-lg-row gap-2 pt-3 pt-lg-0">
                @if ($user && $user->hasVerifiedEmail())
                    <a class="lp-btn lp-btn-primary lp-btn-sm" href="{{ route($user->is_admin ? 'admin.dashboard' : 'dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Go to dashboard
                    </a>
                @elseif ($user)
                    {{-- Logged in but email not verified yet: the app is locked, so just offer Log out. --}}
                    <form method="POST" action="{{ route('logout') }}" class="d-grid">
                        @csrf
                        <button type="submit" class="lp-btn lp-btn-ghost lp-btn-sm">Log out</button>
                    </form>
                @else
                    <a class="lp-btn lp-btn-ghost lp-btn-sm" href="{{ route('login') }}">Log in</a>
                    <a class="lp-btn lp-btn-primary lp-btn-sm" href="{{ route('register') }}">Register</a>
                @endif
            </div>
        </div>
    </div>
</nav>
