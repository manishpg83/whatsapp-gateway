<?php

namespace App\Http\Controllers;

use App\Support\Guides;
use Illuminate\View\View;

/**
 * Public tutorials (/guides) — see App\Support\Guides for the list.
 */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guides.index', ['guides' => Guides::all()]);
    }

    public function show(string $slug): View
    {
        $guide = Guides::find($slug);

        abort_unless($guide, 404);

        return view("guides.{$slug}", [
            'guide' => $guide,
            // "More guides" links at the end of the page.
            'others' => array_diff_key(Guides::all(), [$slug => true]),
        ]);
    }
}
