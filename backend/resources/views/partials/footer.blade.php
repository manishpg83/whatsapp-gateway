{{-- Shared footer — included on both the guest chrome and the logged-in
     content column (layouts/app.blade.php). Keep them in sync by editing
     only this file, never inlining a second copy. --}}
<footer class="site-footer text-white-50 py-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-6">
                <a href="{{ route('home') }}" class="d-inline-flex text-decoration-none mb-3" aria-label="{{ config('app.name') }} home">
                    <x-brand-logo theme="light" size="sm" />
                </a>
                <p class="small mb-0" style="max-width: 32rem;">
                    Connect your own WhatsApp number and send &amp; receive messages
                    through a simple REST API — built for developers who want WhatsApp
                    messaging in their own product without the official Business API's
                    approval process.
                </p>
            </div>
            <div class="col-md-3">
                @auth
                    @if (auth()->user()->is_admin)
                        <div class="text-white small fw-semibold text-uppercase mb-2">Admin</div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1"><a href="{{ route('admin.dashboard') }}" class="link-light text-decoration-none">Dashboard</a></li>
                            <li class="mb-1"><a href="{{ route('admin.users.index') }}" class="link-light text-decoration-none">Users</a></li>
                            <li class="mb-1"><a href="{{ route('admin.instances.index') }}" class="link-light text-decoration-none">Instances</a></li>
                            <li class="mb-1"><a href="{{ route('admin.revenue.index') }}" class="link-light text-decoration-none">Revenue</a></li>
                            <li class="mb-1"><a href="{{ route('admin.plans.index') }}" class="link-light text-decoration-none">Billing</a></li>
                            <li><a href="{{ route('admin.audit-log.index') }}" class="link-light text-decoration-none">Audit log</a></li>
                        </ul>
                    @else
                        <div class="text-white small fw-semibold text-uppercase mb-2">Product</div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1"><a href="{{ route('dashboard') }}" class="link-light text-decoration-none">Dashboard</a></li>
                            <li class="mb-1"><a href="{{ route('instances.index') }}" class="link-light text-decoration-none">Instances</a></li>
                            <li class="mb-1"><a href="{{ route('messages.index') }}" class="link-light text-decoration-none">Messages</a></li>
                            <li class="mb-1"><a href="{{ route('bulk.index') }}" class="link-light text-decoration-none">Bulk messages</a></li>
                            <li class="mb-1"><a href="{{ route('billing.index') }}" class="link-light text-decoration-none">Billing</a></li>
                            <li class="mb-1"><a href="{{ route('docs.index') }}" class="link-light text-decoration-none">API Docs</a></li>
                            <li><a href="{{ route('api-logs.index') }}" class="link-light text-decoration-none">API Logs</a></li>
                        </ul>
                    @endif
                @else
                    <div class="text-white small fw-semibold text-uppercase mb-2">Account</div>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-1"><a href="{{ route('login') }}" class="link-light text-decoration-none">Log in</a></li>
                        <li><a href="{{ route('register') }}" class="link-light text-decoration-none">Register</a></li>
                    </ul>
                @endauth
            </div>
            <div class="col-md-3">
                <div class="text-white small fw-semibold text-uppercase mb-2">Help &amp; legal</div>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><a href="{{ route('about') }}" class="link-light text-decoration-none">About us</a></li>
                    <li class="mb-1"><a href="{{ route('pricing') }}" class="link-light text-decoration-none">Pricing</a></li>
                    <li class="mb-1"><a href="{{ route('docs.index') }}" class="link-light text-decoration-none">API Docs</a></li>
                    <li class="mb-1"><a href="{{ route('contact') }}" class="link-light text-decoration-none">Contact us</a></li>
                    <li class="mb-1"><a href="{{ route('terms') }}" class="link-light text-decoration-none">Terms of Service</a></li>
                    <li><a href="{{ route('privacy') }}" class="link-light text-decoration-none">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary-subtle my-3">
        <div class="small">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</div>
    </div>
</footer>
