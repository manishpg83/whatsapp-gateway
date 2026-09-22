@extends('layouts.app')

@section('title', 'New instance')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h1 class="h3 mb-4">New instance</h1>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('instances.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name') }}"
                               placeholder="e.g. My Business WhatsApp" required autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Just a label for you — pick anything that helps you tell your instances apart.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Create &amp; show QR code</button>
                    <a href="{{ route('instances.index') }}" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
