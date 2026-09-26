<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    {{-- App pages are private (noindex); the public Terms / Privacy /
         Contact pages opt back in with @section('robots', 'index, follow'). --}}
    @include('partials.seo', ['defaultRobots' => 'noindex, nofollow'])
    @php
        // Verified users get the app shell (sidebar); guests and unverified
        // users get the public site's top bar, which needs landing.css + its font.
        $appShell = auth()->check() && auth()->user()->hasVerifiedEmail();
    @endphp
    @unless ($appShell)
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @endunless
    @vite($appShell ? ['resources/css/app.css', 'resources/js/app.js'] : ['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
{{-- The full app shell is for verified users only. A logged-in user who
     hasn't verified their email yet can't open any of its pages, so they
     get the simple top bar below (with just "Log out") instead. --}}
@if ($appShell)
    <div class="d-flex flex-grow-1 app-shell">
        {{-- Sidebar: a static column at md+, a slide-in offcanvas below it --}}
        <div class="offcanvas-md offcanvas-start sidebar-shell" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header d-md-none">
                <h5 class="offcanvas-title text-white" id="sidebarMenuLabel">{{ config('app.name') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-3">
                {{-- Logo opens the public landing page (logged-in users can view it too). --}}
                <a href="{{ route('home') }}" class="d-none d-md-flex align-items-center gap-2 text-white text-decoration-none mb-4" title="View website">
                    <span class="sidebar-logo-badge"><i class="bi bi-chat-dots-fill"></i></span>
                    <span class="fw-bold lh-sm">WhatsApp<br>Gateway</span>
                </a>

                {{-- An admin account is a platform-management account, not a
                     customer account — its sidebar is entirely the admin
                     section (no Instances/personal Billing/API Docs, no
                     nested "Admin" link to a second, separate nav). --}}
                <ul class="nav nav-pills flex-column gap-1 mb-auto">
                    @if (auth()->user()->is_admin)
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-house-door me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                <i class="bi bi-people me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.instances.*') ? 'active' : '' }}" href="{{ route('admin.instances.index') }}">
                                <i class="bi bi-hdd-stack me-2"></i>Instances
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.revenue.*') ? 'active' : '' }}" href="{{ route('admin.revenue.index') }}">
                                <i class="bi bi-graph-up-arrow me-2"></i>Revenue
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}">
                                <i class="bi bi-credit-card me-2"></i>Billing
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.audit-log.*') ? 'active' : '' }}" href="{{ route('admin.audit-log.index') }}">
                                <i class="bi bi-journal-text me-2"></i>Audit log
                            </a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <i class="bi bi-house-door me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('instances.*') ? 'active' : '' }}" href="{{ route('instances.index') }}">
                                <i class="bi bi-hdd-stack me-2"></i>Instances
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('messages.*') ? 'active' : '' }}" href="{{ route('messages.index') }}">
                                <i class="bi bi-chat-left-text me-2"></i>Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('billing.*') ? 'active' : '' }}" href="{{ route('billing.index') }}">
                                <i class="bi bi-credit-card me-2"></i>Billing
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('docs.*') ? 'active' : '' }}" href="{{ route('docs.index') }}">
                                <i class="bi bi-code-slash me-2"></i>API Docs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link {{ request()->routeIs('api-logs.*') ? 'active' : '' }}" href="{{ route('api-logs.index') }}">
                                <i class="bi bi-clock-history me-2"></i>API Logs
                            </a>
                        </li>
                    @endif
                </ul>

            </div>
        </div>

        {{-- Content column --}}
        <div class="d-flex flex-column flex-grow-1 app-content">
            <header class="d-flex align-items-center bg-white border-bottom px-3 px-md-4 py-2 sticky-top">
                <button class="btn btn-outline-secondary d-md-none me-2" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <i class="bi bi-list fs-5"></i>
                </button>

                <span class="text-muted small text-uppercase fw-semibold d-none d-sm-inline">@yield('title', 'Dashboard')</span>

                <div class="ms-auto dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-badge">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span>{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.edit') }}">
                                <i class="bi bi-gear me-2"></i>Account
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('home') }}">
                                <i class="bi bi-globe2 me-2"></i>View website
                            </a>
                        </li>
                        @unless (auth()->user()->is_admin)
                            <li>
                                <a class="dropdown-item {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">
                                    <i class="bi bi-headset me-2"></i>Contact support
                                </a>
                            </li>
                        @endunless
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-2"></i>Log out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="flex-grow-1 p-3 p-md-4">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>

            {{-- Admin panel: a slim "powered by" line inside the content
                 column (next to the sidebar), no page links. --}}
            @if (auth()->user()->is_admin)
                @include('partials.footer-admin')
            @endif
        </div>
    </div>

    {{-- Everyone else: the full footer, full width below the sidebar too. --}}
    @unless (auth()->user()->is_admin)
        @include('partials.footer')
    @endunless
@else
    @include('partials.site-nav')

    <main class="container py-5 flex-grow-1">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    @include('partials.footer')
@endif
</body>
</html>
