{{-- Live preview of the pricing card for the create/edit form. Starts
     with the current values (so it's right without JS), then a small
     script mirrors every [data-preview] input as the admin types. --}}
@php
    $previewPrice = (int) old('price', $plan->price ?? 0);
    $previewInstances = (int) old('instances', $plan->instances ?? 1);
    // Same tier icons as the pricing cards; new plans get a generic one.
    $previewIcon = match ($plan->slug ?? null) {
        'free' => 'bi-gift',
        'starter' => 'bi-lightning-charge',
        'growth' => 'bi-graph-up-arrow',
        'business' => 'bi-building',
        default => 'bi-layers',
    };
@endphp
<div class="ad-preview">
    <div class="ad-action-title"><i class="bi bi-eye"></i> Live preview</div>

    <div class="bl-plan {{ old('popular', $plan->popular ?? false) ? 'bl-plan-popular' : '' }}" data-pv-card>
        <span class="bl-plan-badge {{ old('popular', $plan->popular ?? false) ? '' : 'd-none' }}" data-pv-badge><i class="bi bi-star-fill"></i> Most popular</span>

        <span class="bl-plan-icon"><i class="bi {{ $previewIcon }}"></i></span>
        <div class="bl-plan-name" data-pv-name>{{ old('name', $plan->name ?? '') ?: 'Plan name' }}</div>
        <p class="bl-plan-desc" data-pv-description>{{ old('description', $plan->description ?? '') ?: 'A one-line tagline' }}</p>
        <div class="bl-plan-price" data-pv-price>
            @if ($previewPrice > 0)
                &#8377;{{ number_format($previewPrice) }}<span>/mo</span>
            @else
                Free
            @endif
        </div>
        <ul class="bl-plan-list mb-3">
            <li><i class="bi bi-check-lg"></i><span data-pv-instances>{{ $previewInstances }} {{ Str::plural('instance', $previewInstances) }}</span></li>
            <li><i class="bi bi-check-lg"></i><span data-pv-messages>{{ number_format((int) old('messages_per_month', $plan->messages_per_month ?? 50)) }} messages/mo</span></li>
            <li><i class="bi bi-check-lg"></i>Full REST API access</li>
            <li><i class="bi bi-check-lg"></i>Webhook delivery</li>
        </ul>
        <span class="btn bl-plan-btn btn-outline-primary w-100 pe-none" aria-hidden="true">Choose plan</span>
    </div>

    <p class="small text-muted mt-3 mb-0">This is how the card looks to customers on their Billing page.</p>
</div>

<script>
(function () {
    const card = document.querySelector('[data-pv-card]');
    const fmt = new Intl.NumberFormat('en-IN');
    const value = (key) => document.querySelector(`[data-preview="${key}"]`);
    const set = (key, text) => { card.querySelector(`[data-pv-${key}]`).textContent = text; };

    function render() {
        set('name', value('name').value.trim() || 'Plan name');
        set('description', value('description').value.trim() || 'A one-line tagline');

        const price = Math.max(0, parseInt(value('price').value, 10) || 0);
        card.querySelector('[data-pv-price]').innerHTML = price > 0
            ? `&#8377;${fmt.format(price)}<span>/mo</span>`
            : 'Free';

        const instances = Math.max(0, parseInt(value('instances').value, 10) || 0);
        set('instances', `${instances} instance${instances === 1 ? '' : 's'}`);

        const messages = Math.max(0, parseInt(value('messages').value, 10) || 0);
        set('messages', `${fmt.format(messages)} messages/mo`);

        const popular = value('popular').checked;
        card.classList.toggle('bl-plan-popular', popular);
        card.querySelector('[data-pv-badge]').classList.toggle('d-none', !popular);
    }

    document.querySelectorAll('[data-preview]').forEach((input) => {
        input.addEventListener('input', render);
        input.addEventListener('change', render);
    });
})();
</script>
