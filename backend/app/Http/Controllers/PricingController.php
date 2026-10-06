<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

/**
 * Public /pricing page: the plan cards, a feature comparison table and
 * pricing FAQs. Prices follow the site (₹ by default, Rand on .za — see
 * App\Support\Currency).
 */
class PricingController extends Controller
{
    public function __invoke(): View
    {
        // Same as the home page: logged-in users get "Go to dashboard".
        $dashboardUrl = auth()->check()
            ? route(auth()->user()->is_admin ? 'admin.dashboard' : 'dashboard')
            : null;

        return view('pricing.index', [
            'plans' => Plan::orderBy('price')->get()->keyBy('slug'),
            'dashboardUrl' => $dashboardUrl,
        ]);
    }
}
