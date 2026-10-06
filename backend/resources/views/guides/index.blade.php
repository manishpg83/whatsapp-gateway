@extends('layouts.landing')

@section('title', 'WhatsApp API Guides & Tutorials')
@section('meta_description', 'Step-by-step tutorials to send and receive WhatsApp messages from your own code: PHP, Python, Node.js and more. Copy-paste examples, free plan.')

@push('structured_data')
@include('partials.breadcrumb-schema', ['crumb' => 'Guides'])
@endpush

@section('content')
<section class="lp-section">
    <div class="container">
        <div class="lp-section-head" data-reveal>
            <span class="lp-eyebrow"><i class="bi bi-journal-code"></i> Guides</span>
            <h1 class="lp-h2">WhatsApp API guides &amp; tutorials</h1>
            <p class="lp-sub">Step-by-step examples to send WhatsApp messages from your own code. Pick your language.</p>
        </div>

        <div class="row g-4 justify-content-center">
            @foreach ($guides as $slug => $guide)
                <div class="col-md-6 col-xl-3" data-reveal style="--i: {{ $loop->index }};">
                    <a href="{{ route('guides.show', $slug) }}" class="card h-100 text-decoration-none shadow-sm border-0 gd-card">
                        <div class="card-body p-4">
                            <i class="bi {{ $guide['icon'] }} fs-1 text-primary"></i>
                            <h2 class="h5 fw-bold text-body mt-3 mb-2">{{ $guide['title'] }}</h2>
                            <p class="small text-muted mb-3">{{ $guide['description'] }}</p>
                            <span class="small fw-semibold">Read the guide <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <p class="text-center mt-5 mb-0">
            Using another language? Every endpoint has examples in curl, JavaScript, PHP, Python, C# and Java in the
            <a href="{{ route('docs.index') }}">API documentation</a>.
        </p>
    </div>
</section>
@endsection
