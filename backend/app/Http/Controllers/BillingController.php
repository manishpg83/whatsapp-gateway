<?php

namespace App\Http\Controllers;

use App\Services\CashfreeClient;
use App\Services\PlanLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function index(Request $request, PlanLimiter $limiter): View
    {
        $user = $request->user();

        return view('billing.index', [
            'subscription' => $user->subscription,
            'plans' => config('plans'),
            'instanceCount' => $user->whatsappSessions()->count(),
            'messageCount' => $limiter->messagesSentThisMonth($user),
        ]);
    }

    /**
     * Starts a Cashfree subscription checkout for a paid plan. Renders
     * the checkout page directly (rather than redirecting) since it needs
     * the subscription_session_id this call just got back from Cashfree.
     */
    public function subscribe(Request $request, string $plan, CashfreeClient $cashfree): View|RedirectResponse
    {
        $planDetails = config("plans.{$plan}");

        abort_if(! $planDetails || $planDetails['price'] <= 0, 404);

        $data = $request->validate([
            'phone' => ['required', 'regex:/^\d{7,15}$/'],
        ]);

        $user = $request->user();
        $subscriptionId = 'sub_'.$user->id.'_'.Str::random(10);

        try {
            $result = $cashfree->createSubscription(
                subscriptionId: $subscriptionId,
                plan: $planDetails,
                customerName: $user->name,
                customerEmail: $user->email,
                customerPhone: $data['phone'],
                returnUrl: route('billing.return'),
            );
        } catch (Throwable $e) {
            Log::error('Cashfree: failed to create subscription', [
                'user_id' => $user->id,
                'plan' => $plan,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('billing.index')
                ->with('error', 'Could not start checkout — please try again.');
        }

        // Not active yet — that only happens once the webhook confirms
        // the customer actually authorized the payment at Cashfree.
        $user->subscription()->update([
            'plan' => $plan,
            'cashfree_subscription_id' => $subscriptionId,
            'status' => 'pending',
        ]);

        return view('billing.checkout', [
            'subscriptionSessionId' => $result['subscription_session_id'],
            'cashfreeMode' => config('services.cashfree.env'),
        ]);
    }

    /**
     * Cancels the user's active/pending paid subscription at Cashfree,
     * then reverts them to the free plan locally. Immediate, not
     * end-of-period — `current_period_end` isn't reliably populated yet
     * (the webhook that would confirm it has never been verified live,
     * see progress.md), so there's no trustworthy date to grace-period
     * against. Simpler and still honest: the Terms of Service only
     * promise no refund for the current period, not continued access.
     */
    public function cancel(Request $request, CashfreeClient $cashfree): RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if ($subscription->plan === 'free' || ! $subscription->cashfree_subscription_id) {
            return redirect()->route('billing.index')
                ->with('error', 'You do not have an active paid subscription to cancel.');
        }

        try {
            $cashfree->cancelSubscription($subscription->cashfree_subscription_id);
        } catch (Throwable $e) {
            Log::error('Cashfree: failed to cancel subscription', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->cashfree_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('billing.index')
                ->with('error', 'Could not cancel your subscription — please try again or contact support.');
        }

        $subscription->update([
            'plan' => 'free',
            'status' => 'active',
            'cashfree_subscription_id' => null,
            'current_period_end' => null,
        ]);

        return redirect()->route('billing.index')
            ->with('status', 'Your subscription has been cancelled. You are now on the Free plan.');
    }

    /**
     * Where Cashfree's hosted checkout sends the browser back to when the
     * customer finishes (or abandons) authorizing the payment. Cashfree
     * does this as a POST, not a GET redirect, so this route has to
     * accept both. This page is UX only, never the source of truth for
     * whether the payment actually succeeded — that's CashfreeWebhookController,
     * which may not have arrived yet by the time this loads (the billing
     * page just shows whatever the current status is at that moment).
     */
    public function return(): RedirectResponse
    {
        return redirect()->route('billing.index');
    }
}
