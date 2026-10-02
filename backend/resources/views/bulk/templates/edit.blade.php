@extends('layouts.app')

@section('title', 'Edit saved message · Bulk messages')

@section('content')
<a href="{{ route('bulk.templates.index') }}" class="bk-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Saved messages
</a>

<div class="row">
    <div class="col-lg-7 col-xl-6">
        <div class="d-flex align-items-center gap-3 mb-4 db-in" style="--i: 1;">
            <span class="ms-head-icon"><i class="bi bi-bookmark-star"></i></span>
            <h1 class="h3 mb-0 text-break">Edit "{{ $template->name }}"</h1>
        </div>

        <div class="card shadow-sm db-in" style="--i: 2;">
            <form method="POST" action="{{ route('bulk.templates.update', $template->id) }}">
                @csrf
                @method('PUT')
                <div class="card-body p-3 p-md-4">
                    @include('bulk.templates._fields', ['template' => $template])
                    <div class="form-text mt-2">Changes apply to new campaigns only — campaigns already created keep their own copy.</div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2 p-3 p-md-4 pt-md-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                    <a href="{{ route('bulk.templates.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
