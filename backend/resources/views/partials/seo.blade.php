{{--
    SEO tags for every page, included in the <head> of all three layouts.
    Pages can set (all optional):
      @section('meta_description', '…')   ~150 characters, unique per page
      @section('robots', 'index, follow')  default comes from the layout ($defaultRobots)
      @section('og_title', '…')           defaults to the page <title>
      @push('structured_data') <script type="application/ld+json">…</script> @endpush
    Private pages (dashboard, admin, password reset…) are noindex by default,
    so search engines only list the public marketing / legal pages.
--}}
@php
    // Short @section('x', 'value') values come back already HTML-escaped;
    // decode them once so {{ }} below escapes them exactly once.
    $section = fn (string $name, string $default = '') => html_entity_decode(trim($__env->yieldContent($name, $default)), ENT_QUOTES | ENT_HTML5);

    $appName = config('app.name');
    $pageTitle = $section('title', $appName);
    $fullTitle = $pageTitle === $appName ? $appName : $pageTitle.' - '.$appName;
    $description = $section('meta_description')
        ?: 'Connect your own WhatsApp number and send & receive WhatsApp messages through a simple REST API with webhooks — live in minutes, no Business API approval.';
    $robots = $section('robots') ?: ($defaultRobots ?? 'index, follow');
    $ogTitle = $section('og_title') ?: $fullTitle;
    // Canonical = this URL without its query string (?topic=…, ?page=…).
    $canonical = url()->current();
    $ogImage = asset('images/og-image.png');
@endphp
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="theme-color" content="#0b8457">
<meta name="application-name" content="{{ $appName }}">
<meta name="author" content="BriskBrain Technologies">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

{{-- Open Graph (WhatsApp, Facebook, LinkedIn link previews) --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $appName }}">
<meta property="og:locale" content="en_IN">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $appName }} — WhatsApp messaging API for developers">

{{-- Twitter / X card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">

@stack('structured_data')
