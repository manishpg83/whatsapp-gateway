{{-- The plan cards (home page pricing section and /pricing). Expects $plans
     (Plan models keyed by slug) and $dashboardUrl (null for guests). --}}
<div class="row g-4 align-items-stretch">
    @foreach ($plans as $key => $plan)
        @php
            $isPopular = ! empty($plan['popular']);
            $tierIcon = match ($key) {
                'free' => 'bi-gift',
                'starter' => 'bi-lightning-charge',
                'growth' => 'bi-graph-up-arrow',
                default => 'bi-building',
            };
        @endphp
        <div class="col-sm-6 col-xl-3">
            <div class="lp-price {{ $isPopular ? 'lp-price-popular' : '' }}" data-reveal style="--i: {{ $loop->index }};">
                @if ($isPopular)
                    <span class="lp-price-badge"><i class="bi bi-star-fill"></i> Most popular</span>
                @endif
                <span class="lp-price-icon"><i class="bi {{ $tierIcon }}"></i></span>
                <div class="lp-price-name">{{ $plan['name'] }}</div>
                <p class="lp-price-desc">{{ $plan['description'] }}</p>
                <div class="lp-price-amount">
                    <x-plan-price :plan="$plan" />
                </div>
                <ul class="lp-price-list">
                    <li><i class="bi bi-check-lg"></i>{{ $plan['instances'] }} instance{{ $plan['instances'] > 1 ? 's' : '' }}</li>
                    <li><i class="bi bi-check-lg"></i>{{ number_format($plan['messages_per_month']) }} messages/mo</li>
                    <li><i class="bi bi-check-lg"></i>{{ \App\Models\Plan::chatbotLabel($plan['chatbot_entries'] ?? 0) }}</li>
                    <li><i class="bi bi-check-lg"></i>Full REST API access</li>
                    <li><i class="bi bi-check-lg"></i>Webhook delivery</li>
                </ul>
                <a href="{{ $dashboardUrl ?? route('register') }}" class="lp-btn {{ $isPopular ? 'lp-btn-primary' : 'lp-btn-outline' }} w-100 mt-auto">{{ $dashboardUrl ? 'Go to dashboard' : 'Get started' }}</a>
            </div>
        </div>
    @endforeach
</div>
