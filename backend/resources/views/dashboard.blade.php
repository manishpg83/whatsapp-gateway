@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="card shadow-sm">
    <div class="card-body p-4">
        <h1 class="h4">Welcome, {{ auth()->user()->name }}</h1>
        <p class="text-muted mb-0">You are logged in as {{ auth()->user()->email }}.</p>
        <p class="text-muted mb-0">This is a placeholder page. The real dashboard comes in M3.</p>
    </div>
</div>
@endsection
