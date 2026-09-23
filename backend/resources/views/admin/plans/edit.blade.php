@extends('layouts.app')

@section('title', 'Admin · Edit ' . $plan->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <h1 class="h3 mb-4">Edit {{ $plan->name }}</h1>

        <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
                    <i class="bi bi-pencil"></i>
                </div>
                <div>
                    <div class="fw-semibold">Edit plan</div>
                    <div class="text-muted small">Slug: <code>{{ $plan->slug }}</code> — used by the API and can't be changed here.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.plans._form')

                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-link">Cancel</a>
                </form>

                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Changing the price does not affect existing subscribers on this plan — see the note
                    on the <a href="{{ route('admin.plans.index') }}">Plans</a> page.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
