@extends('layouts.app')

@section('title', 'Admin · Company settings')

@section('content')
@php
    $address = $company['address'];

    // name => [label, current value, input type, required, placeholder, column width]
    $contactFields = [
        'name' => ['Company name', $company['name'], 'text', true, 'BriskBrain Technologies', 'col-12'],
        'email' => ['Email', $company['email'], 'email', true, 'hello@example.com', 'col-md-6'],
        'phone' => ['Phone', $company['phone'], 'tel', true, '+91 94288 89935', 'col-md-6'],
        'website' => ['Company website', $company['website'], 'url', true, 'https://example.com/', 'col-12'],
    ];
    $addressFields = [
        'street' => ['Street address', $address['street'], 'text', true, 'Office no., building, road, area', 'col-12'],
        'city' => ['City', $address['city'], 'text', true, 'Ahmedabad', 'col-md-6'],
        'region' => ['State / province', $address['region'], 'text', false, 'Gujarat', 'col-md-6'],
        'postal_code' => ['PIN / postal code', $address['postal_code'], 'text', false, '382470', 'col-md-4'],
        'country' => ['Country', $address['country'], 'text', true, 'India', 'col-md-5'],
        'country_code' => ['Country code', $address['country_code'], 'text', true, 'IN', 'col-md-3'],
    ];
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4 db-in">
    <div>
        <h1 class="h3 mb-1">Company settings</h1>
        <p class="text-muted mb-0">Your company details, shown on the About, Privacy and Terms pages and to search engines.</p>
    </div>
    <div class="flex-shrink-0">
        @if ($saved)
            <span class="ad-pill ad-tone-blue"><i class="bi bi-pencil"></i>Edited {{ $saved->updated_at->diffForHumans() }}@if ($saved->editor) by {{ $saved->editor->name }}@endif</span>
        @else
            <span class="ad-pill ad-tone-grey">Using the built-in details</span>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('admin.company.update') }}">
    @csrf
    @method('PUT')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ad-panel h-auto db-in" style="--i: 1;">
                <div class="ad-panel-head">
                    <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-building"></i></span>
                    <div class="fw-semibold">Company details</div>
                </div>
                <div class="p-3 p-md-4">
                    @foreach (['Contact' => [$contactFields, 'bi-telephone'], 'Address' => [$addressFields, 'bi-geo-alt']] as $section => [$fields, $icon])
                        <div class="ad-form-section">
                            <div class="ad-action-title"><i class="bi {{ $icon }}"></i> {{ $section }}</div>
                            <div class="row g-3">
                                @foreach ($fields as $field => [$label, $value, $type, $required, $placeholder, $col])
                                    <div class="{{ $col }}">
                                        <label for="{{ $field }}" class="form-label">{{ $label }}@unless ($required) <span class="text-muted small">(optional)</span>@endunless</label>
                                        <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $value) }}"
                                               placeholder="{{ $placeholder }}" @required($required)
                                               @if ($field === 'country_code') maxlength="2" style="text-transform: uppercase;" @endif
                                               class="form-control @error($field) is-invalid @enderror">
                                        @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @if ($field === 'phone')
                                            <div class="form-text">With + and the country code.</div>
                                        @elseif ($field === 'country_code')
                                            <div class="form-text">2 letters, e.g. IN.</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="ad-panel-foot d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary ad-btn-lift"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                    <a href="{{ route('about') }}" class="btn btn-light border" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View About page</a>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ad-sticky db-in" style="--i: 2;">
                <div class="ad-panel h-auto">
                    <div class="ad-panel-head">
                        <span class="ad-stat-icon ad-tone-green"><i class="bi bi-eye"></i></span>
                        <div class="fw-semibold">Shown on the website now</div>
                    </div>
                    <div class="p-3 p-md-4 small">
                        <div class="fw-semibold mb-2">{{ $company['name'] }}</div>
                        <address class="mb-3 text-muted">
                            @foreach (\App\Support\CompanySettings::addressLines() as $line)
                                {{ $line }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </address>
                        <div class="mb-1"><i class="bi bi-telephone me-2 text-primary"></i>{{ $company['phone'] }}</div>
                        <div class="mb-1 text-break"><i class="bi bi-envelope me-2 text-primary"></i>{{ $company['email'] }}</div>
                        <div class="text-break"><i class="bi bi-globe2 me-2 text-primary"></i>{{ preg_replace('#^https?://|/$#', '', $company['website']) }}</div>
                    </div>
                </div>
                <div class="ad-alert ad-tone-amber mt-3">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        The contact form still sends messages to the address in <code>MAIL_SUPPORT_ADDRESS</code> (the <code>.env</code> file).
                        Change that there if it should go to this email too.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
