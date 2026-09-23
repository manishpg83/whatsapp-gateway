<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TermsController extends Controller
{
    /**
     * The date this page's content was last actually changed — bump this
     * (not `now()`) whenever the Terms text itself changes. A "Last
     * updated" date is meaningless if it just always shows today.
     */
    private const LAST_UPDATED = '2026-09-23';

    public function __invoke(): View
    {
        return view('terms.index', [
            'lastUpdated' => Carbon::parse(self::LAST_UPDATED),
        ]);
    }
}
