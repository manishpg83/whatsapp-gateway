@extends('layouts.landing')

@section('title', 'WhatsApp API Guides & Tutorials')
@section('meta_description', 'Step-by-step tutorials to send and receive WhatsApp messages from your own code: PHP, Python, Node.js and more. Copy-paste examples, free plan.')

@push('structured_data')
@include('partials.breadcrumb-schema', ['crumb' => 'Guides'])
@endpush

@php
    // Look of each guide's card: colour tone, the short badge in its
    // colour band, and a tag. A guide not listed here gets the default.
    $looks = [
        'send-whatsapp-message-php' => ['tone' => 'purple', 'badge' => 'PHP', 'tag' => 'Code tutorial'],
        'send-whatsapp-message-python' => ['tone' => 'blue', 'badge' => 'PY', 'tag' => 'Code tutorial'],
        'send-whatsapp-message-nodejs' => ['tone' => 'green', 'badge' => 'JS', 'tag' => 'Code tutorial'],
        'whatsapp-auto-reply-chatbot' => ['tone' => 'teal', 'badge' => null, 'tag' => 'No code'],
        'receive-whatsapp-messages-webhook' => ['tone' => 'amber', 'badge' => null, 'tag' => 'Code tutorial'],
    ];
@endphp

@section('content')
<section class="lp-section gd-index">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-journal-code"></i> Guides</span>
            <h1 class="lp-h2">WhatsApp API guides &amp; tutorials</h1>
            <p class="lp-sub">Step-by-step examples to send WhatsApp messages from your own code. Pick your language.</p>
        </div>

        {{-- Scroller: every guide in one swipeable row, with prev/next buttons (landing.js). --}}
        <div class="gd-scroller" data-gd-scroller data-reveal style="--i: 1;">
            <div class="gd-scroller-bar">
                <span class="gd-count"><i class="bi bi-collection"></i> {{ count($guides) }} guides</span>
                <div class="gd-arrows">
                    <button type="button" class="gd-arrow" data-gd-prev aria-label="Previous guides" disabled>
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="gd-arrow" data-gd-next aria-label="More guides">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="gd-track" data-gd-track tabindex="0" role="region" aria-label="Guides — scroll sideways for more">
                @foreach ($guides as $slug => $guide)
                    @php($look = $looks[$slug] ?? ['tone' => 'green', 'badge' => null, 'tag' => 'Guide'])
                    <a href="{{ route('guides.show', $slug) }}" class="gd-tile gd-tone-{{ $look['tone'] }}">
                        <span class="gd-tile-band">
                            <span class="gd-tile-badge">
                                @if ($look['badge'])
                                    {{ $look['badge'] }}
                                @else
                                    <i class="bi {{ $guide['icon'] }}"></i>
                                @endif
                            </span>
                            <span class="gd-tile-tag">{{ $look['tag'] }}</span>
                        </span>
                        <span class="gd-tile-body">
                            <span class="gd-tile-lang">{{ $guide['language'] }}</span>
                            <h2 class="gd-tile-title">{{ $guide['title'] }}</h2>
                            <span class="gd-tile-text">{{ $guide['description'] }}</span>
                            <span class="gd-tile-go">Read the guide <i class="bi bi-arrow-right"></i></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="gd-more" data-reveal style="--i: 2;">
            <span class="gd-more-icon"><i class="bi bi-code-slash"></i></span>
            <div class="gd-more-text">
                <div class="gd-more-title">Using another language?</div>
                <div>Every endpoint has examples in curl, JavaScript, PHP, Python, C# and Java.</div>
            </div>
            <a href="{{ route('docs.index') }}" class="lp-btn lp-btn-outline lp-btn-sm">
                API documentation <i class="bi bi-arrow-right lp-btn-arrow"></i>
            </a>
        </div>
    </div>
</section>
@endsection
