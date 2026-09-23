@extends('layouts.app')

@section('title', 'Admin · New plan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <h1 class="h3 mb-4">New plan</h1>

        <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <div class="fw-semibold">Add a new billing plan</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.plans.store') }}">
                    @csrf
                    @include('admin.plans._form')

                    <button type="submit" class="btn btn-primary">Create plan</button>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
