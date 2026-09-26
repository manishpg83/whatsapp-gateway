<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PrivacyController extends Controller
{
    /**
     * The date this page's content was last actually changed — bump this
     * (not `now()`) whenever the policy text itself changes, same as
     * TermsController.
     */
    public const LAST_UPDATED = '2026-09-24';

    public function __invoke(): View
    {
        return view('privacy.index', [
            'lastUpdated' => Carbon::parse(self::LAST_UPDATED),
        ]);
    }
}
