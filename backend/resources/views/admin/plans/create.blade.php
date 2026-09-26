@extends('layouts.app')

@section('title', 'Admin · New plan')

@section('content')
<a href="{{ route('admin.plans.index') }}" class="ad-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Billing
</a>

<div class="mb-4 db-in" style="--i: 1;">
    <h1 class="h3 mb-1">New plan</h1>
    <p class="text-muted mb-0">Add a new billing plan. It appears on the pricing page as soon as it's saved.</p>
</div>

<form method="POST" action="{{ route('admin.plans.store') }}">
    @csrf
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ad-panel h-auto db-in" style="--i: 2;">
                <div class="ad-panel-head">
                    <span class="ad-stat-icon ad-tone-green"><i class="bi bi-plus-lg"></i></span>
                    <div class="fw-semibold">Add a new billing plan</div>
                </div>
                <div class="p-3 p-md-4">
                    @include('admin.plans._form')
                </div>
                <div class="ad-panel-foot d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary ad-btn-lift"><i class="bi bi-check-lg me-1"></i>Create plan</button>
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
