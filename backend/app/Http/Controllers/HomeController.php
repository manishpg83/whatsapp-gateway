<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Logged-in users can view the landing page too (e.g. via the app's
        // logo); its buttons then point to their dashboard instead of
        // Log in / Register. Admins go to the admin panel.
        $dashboardUrl = auth()->check()
            ? route(auth()->user()->is_admin ? 'admin.dashboard' : 'dashboard')
            : null;

        return view('home', [
            'plans' => Plan::orderBy('price')->get()->keyBy('slug'),
            'dashboardUrl' => $dashboardUrl,
        ]);
    }
}
