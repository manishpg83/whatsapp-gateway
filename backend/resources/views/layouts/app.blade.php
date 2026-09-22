<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ config('app.name') }}</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                @auth
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                               href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('instances.*') ? 'active' : '' }}"
                               href="{{ route('instances.index') }}">Instances</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}"
                               href="{{ route('billing.index') }}">Billing</a>
                        </li>
                        {{-- Placeholder: built in a later milestone. --}}
                        <li class="nav-item">
                            <span class="nav-link disabled">API Docs <span class="badge text-bg-secondary">soon</span></span>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center">
                        <span class="navbar-text me-3">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-light btn-sm">Log out</button>
                        </form>
                    </div>
                @else
                    <div class="ms-auto d-flex align-items-center">
                        <a class="btn btn-outline-light btn-sm me-2" href="{{ route('login') }}">Log in</a>
                        <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Register</a>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    <main class="container py-5">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
