<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            // Admins go to the admin panel, everyone else to /dashboard.
            return redirect()->route(auth()->user()->is_admin ? 'admin.dashboard' : 'dashboard');
        }

        return view('home', ['plans' => Plan::orderBy('price')->get()->keyBy('slug')]);
    }
}
