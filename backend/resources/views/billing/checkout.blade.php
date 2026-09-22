@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h1 class="h4 mb-3">Redirecting to secure checkout&hellip;</h1>
                <p class="text-muted">Cashfree will ask you to authorize the recurring payment (sandbox/test mode — no real money moves).</p>
                <div id="checkout-error" class="alert alert-danger d-none"></div>
                <button id="checkout-button" class="btn btn-primary">Continue to checkout</button>
            </div>
        </div>
    </div>
</div>

<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script>
(function () {
    const cashfree = Cashfree({ mode: @json($cashfreeMode === 'production' ? 'production' : 'sandbox') });

    function startCheckout() {
        cashfree.subscriptionsCheckout({
            subsSessionId: @json($subscriptionSessionId),
            redirectTarget: '_self',
        }).then(function (result) {
            if (result.error) {
                const box = document.getElementById('checkout-error');
                box.textContent = result.error.message || 'Checkout could not be started.';
                box.classList.remove('d-none');
            }
        });
    }

    document.getElementById('checkout-button').addEventListener('click', startCheckout);
    // Also try automatically, so most people never need to click anything.
    startCheckout();
})();
</script>
@endsection
