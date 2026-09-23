<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
@auth
    <div class="d-flex app-shell">
        {{-- Sidebar: a static column at md+, a slide-in offcanvas below it --}}
        <div class="offcanvas-md offcanvas-start sidebar-shell" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header d-md-none">
                <h5 class="offcanvas-title text-white" id="sidebarMenuLabel">{{ config('app.name') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-3">
                <a href="{{ auth()->user()->is_admin ? route('admin.dashboard') : route('dashboard') }}" class="d-none d-md-flex align-items-center gap-2 text-white text-decoration-none mb-4">
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
                            <a class="nav-link sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}">
                                <i class="bi bi-credit-card me-2"></i>Billing
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

                {{-- Just a tagline — Terms/copyright live in the page footer below
                     @yield('content') now, no need to duplicate them here too. --}}
                <div class="sidebar-footer mt-4 pt-3 border-top border-light-subtle text-center">
                    <div class="d-flex justify-content-center align-items-center gap-2 text-white-50 small">
                        <i class="bi bi-chat-dots-fill"></i>
                        <span>{{ config('app.name') }}</span>
                    </div>
                    <div class="text-white-50 small">Connect &bull; Automate &bull; Grow</div>
                </div>
            </div>
        </div>

        {{-- Content column --}}
        <div class="d-flex flex-column flex-grow-1 min-vh-100 app-content">
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

            @include('partials.footer')
        </div>
    </div>
@else
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                <i class="bi bi-chat-dots-fill fs-4"></i>
                <span>{{ config('app.name') }}</span>
            </a>
            <div class="d-flex align-items-center">
                <a class="btn btn-outline-light btn-sm me-2" href="{{ route('login') }}">Log in</a>
                <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Register</a>
            </div>
        </div>
    </nav>

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
@endauth
</body>
</html>
