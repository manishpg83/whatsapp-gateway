<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $instanceCount = $request->user()->whatsappSessions()->count();
        $connectedCount = $request->user()->whatsappSessions()->where('status', 'connected')->count();

        return view('dashboard', [
            'user' => $request->user(),
            'instanceCount' => $instanceCount,
            'connectedCount' => $connectedCount,
        ]);
    }
}
