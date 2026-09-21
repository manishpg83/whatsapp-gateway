<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // M5 will replace these two numbers with real counts of THIS user's
        // instances (from the whatsapp_sessions table, scoped to the owner).
        $instanceCount = 0;
        $connectedCount = 0;

        return view('dashboard', [
            'user' => $request->user(),
            'instanceCount' => $instanceCount,
            'connectedCount' => $connectedCount,
        ]);
    }
}
