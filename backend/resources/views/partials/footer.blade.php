{{-- Shared footer — included on both the guest chrome and the logged-in
     content column (layouts/app.blade.php). Keep them in sync by editing
     only this file, never inlining a second copy. --}}
<footer class="site-footer text-white-50 py-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2 text-white mb-2">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span class="fw-semibold">{{ config('app.name') }}</span>
                </div>
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
                            <li><a href="{{ route('admin.plans.index') }}" class="link-light text-decoration-none">Billing</a></li>
                        </ul>
                    @else
                        <div class="text-white small fw-semibold text-uppercase mb-2">Product</div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1"><a href="{{ route('dashboard') }}" class="link-light text-decoration-none">Dashboard</a></li>
                            <li class="mb-1"><a href="{{ route('instances.index') }}" class="link-light text-decoration-none">Instances</a></li>
                            <li class="mb-1"><a href="{{ route('billing.index') }}" class="link-light text-decoration-none">Billing</a></li>
                            <li><a href="{{ route('docs.index') }}" class="link-light text-decoration-none">API Docs</a></li>
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
                <div class="text-white small fw-semibold text-uppercase mb-2">Legal</div>
                <ul class="list-unstyled small mb-0">
                    <li><a href="{{ route('terms') }}" class="link-light text-decoration-none">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary-subtle my-3">
        <div class="small">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</div>
    </div>
</footer>
