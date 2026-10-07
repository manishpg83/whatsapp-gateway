@extends('layouts.app')

@section('title', 'Edit entry · Chatbot')

@section('content')
<a href="{{ route('chatbot.index', ['instance' => $instance->instance_id]) }}" class="bk-back db-in mb-3">
    <i class="bi bi-arrow-left"></i> Back to Chatbot
</a>

<div class="row">
    <div class="col-lg-7 col-xl-6">
        <div class="d-flex align-items-center gap-3 mb-4 db-in" style="--i: 1;">
            <span class="ms-head-icon"><i class="bi bi-robot"></i></span>
            <div>
                <h1 class="h3 mb-0 text-break">Edit "{{ $rule->question }}"</h1>
                <div class="text-muted small text-break">{{ $instance->name }}</div>
            </div>
        </div>

        <div class="card shadow-sm db-in" style="--i: 2;">
            <form method="POST" action="{{ route('chatbot.rules.update', [$instance->instance_id, $rule->id]) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card-body p-3 p-md-4">
                    @include('chatbot._fields', ['rule' => $rule])
                </div>
                <div class="card-footer bg-transparent d-flex gap-2 p-3 p-md-4 pt-md-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                    <a href="{{ route('chatbot.index', ['instance' => $instance->instance_id]) }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
