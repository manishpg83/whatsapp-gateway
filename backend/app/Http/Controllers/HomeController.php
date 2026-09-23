<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('home', ['plans' => config('plans')]);
    }
}
