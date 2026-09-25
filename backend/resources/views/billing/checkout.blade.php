@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm text-center bl-checkout db-in">
            <div class="card-body p-4 p-sm-5">
                <div class="bl-checkout-art mx-auto mb-4" aria-hidden="true">
                    <span class="bl-checkout-orbit"></span>
                    <span class="bl-checkout-icon"><i class="bi bi-shield-lock"></i></span>
                </div>
                <h1 class="h4 mb-2">Redirecting to secure checkout&hellip;</h1>
                <p class="text-muted mb-4">Cashfree will ask you to authorize the recurring payment (sandbox/test mode — no real money moves).</p>

                <ol class="bl-checkout-steps">
                    <li class="done"><i class="bi bi-check-circle-fill"></i>Plan selected</li>
                    <li class="now"><span class="spinner-border spinner-border-sm"></span>Opening Cashfree</li>
                    <li><i class="bi bi-circle"></i>Authorize payment</li>
                </ol>

                <div id="checkout-error" class="alert alert-danger d-none"></div>
                <button id="checkout-button" class="btn btn-primary d-inline-flex align-items-center gap-2 mx-auto">
                    <i class="bi bi-arrow-right-circle"></i> Continue to checkout
                </button>
                <div class="mt-3">
                    <a href="{{ route('billing.index') }}" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to billing</a>
                </div>
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
