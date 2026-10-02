@extends('layouts.app')

@section('title', 'Admin · Email templates')

@section('content')
{{-- Header --}}
<div class="mb-4 db-in">
    <span class="ad-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin</span>
    <h1 class="h3 mt-2 mb-1">Email templates</h1>
    <p class="text-muted mb-0">The emails we send to users. Edit the wording here — the branded design, logo and footer are added automatically.</p>
</div>

<div class="row g-3 g-lg-4">
    @foreach ($templates as $key => $template)
        @php $row = $saved->get($key); @endphp
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('admin.email-templates.edit', $key) }}" class="et-card db-in" style="--i: {{ $loop->iteration }};">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <span class="ad-stat-icon ad-tone-{{ $row ? 'blue' : 'green' }}"><i class="bi {{ $template['icon'] }}"></i></span>
                    @if ($row)
                        <span class="ad-pill ad-tone-blue"><i class="bi bi-pencil"></i>Edited</span>
                    @else
                        <span class="ad-pill ad-tone-grey">Default</span>
                    @endif
                </div>
                <div class="et-card-title">{{ $template['label'] }}</div>
                <p class="et-card-when">{{ $template['sent_when'] }}</p>
                <div class="et-card-subject" title="Subject">
                    <i class="bi bi-envelope"></i>
                    <span>{{ $row->subject ?? $template['subject'] }}</span>
                </div>
                <div class="et-card-foot">
                    <span class="text-muted small">
                        @if ($row)
                            Edited {{ $row->updated_at->diffForHumans() }}@if ($row->editor) by {{ $row->editor->name }}@endif
                        @else
                            Built-in text
                        @endif
                    </span>
                    <span class="et-card-edit">Edit <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
