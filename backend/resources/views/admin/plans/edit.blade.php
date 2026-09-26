@extends('layouts.app')

@section('title', 'Admin · Edit ' . $plan->name)

@section('content')
<a href="{{ route('admin.plans.index') }}" class="ad-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Billing
</a>

<div class="mb-4 db-in" style="--i: 1;">
    <h1 class="h3 mb-1">Edit {{ $plan->name }}</h1>
    <p class="text-muted mb-0">Slug: <code>{{ $plan->slug }}</code> — used by the API and can't be changed here.</p>
</div>

<form method="POST" action="{{ route('admin.plans.update', $plan) }}">
    @csrf
    @method('PUT')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ad-panel h-auto db-in" style="--i: 2;">
                <div class="ad-panel-head">
                    <span class="ad-stat-icon ad-tone-blue"><i class="bi bi-pencil"></i></span>
                    <div class="fw-semibold">Edit plan</div>
                </div>
                <div class="p-3 p-md-4">
                    @include('admin.plans._form')

                    <div class="ad-alert ad-tone-amber mt-4">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            Changing the price does not affect existing subscribers on this plan — see the note
                            on the <a href="{{ route('admin.plans.index') }}">Plans</a> page.
                        </div>
                    </div>
                </div>
                <div class="ad-panel-foot d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary ad-btn-lift"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="ad-sticky db-in" style="--i: 3;">
                @include('admin.plans._preview')
            </div>
        </div>
    </div>
</form>
@endsection
