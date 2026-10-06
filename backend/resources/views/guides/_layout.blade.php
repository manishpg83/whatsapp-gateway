{{-- Frame for one guide page. The guide's own view fills @section('guide_body').
     Expects $guide (App\Support\Guides::find()) and $others (the other guides). --}}
@extends('layouts.landing')

@section('title', $guide['title'])
@section('meta_description', $guide['description'])

@php
    $updated = \Illuminate\Support\Carbon::parse(\App\Support\Guides::UPDATED);
@endphp

@push('structured_data')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'TechArticle',
            'headline' => $guide['title'],
            'description' => $guide['description'],
            'url' => url()->current(),
            'inLanguage' => \App\Support\Site::language(),
            'datePublished' => $updated->toDateString(),
            'dateModified' => $updated->toDateString(),
            'proficiencyLevel' => 'Beginner',
            'author' => ['@type' => 'Organization', 'name' => config('company.name'), 'url' => config('company.website')],
            'publisher' => ['@id' => route('home').'#organization'],
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => route('guides.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $guide['title'], 'item' => url()->current()],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<section class="lp-section">
    <div class="container">
        <div class="row justify-content-center">
            <article class="col-lg-9 col-xl-8 gd-article">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb small mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('guides.index') }}">Guides</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $guide['language'] }}</li>
                    </ol>
                </nav>

                <header class="mb-4">
                    <span class="lp-eyebrow"><i class="bi {{ $guide['icon'] }}"></i> {{ $guide['language'] }} tutorial</span>
                    <h1 class="lp-h2 mt-2 mb-3">{{ $guide['title'] }}</h1>
                    <p class="lp-sub mb-2">{{ $guide['description'] }}</p>
                    <div class="small text-muted">Updated {{ $updated->format('j F Y') }} · by {{ config('company.name') }}</div>
                </header>

                @yield('guide_body')

                @include('guides._responsible')
                @include('guides._more', ['others' => $others])
            </article>
        </div>
    </div>
</section>
@endsection
